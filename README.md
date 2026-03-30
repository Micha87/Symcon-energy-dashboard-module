<?php

declare(strict_types=1);

class EnergyDashboard extends IPSModule
{
    private const IDENT_HTML = 'Visualization';
    private const TIMER_REFRESH = 'Refresh';

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Title', 'Energie Dashboard');
        $this->RegisterPropertyInteger('PvPowerID', 0);
        $this->RegisterPropertyInteger('GridPowerID', 0);
        $this->RegisterPropertyInteger('LoadPowerID', 0);
        $this->RegisterPropertyInteger('BatteryPowerID', 0);
        $this->RegisterPropertyInteger('ArchiveControlID', 0);
        $this->RegisterPropertyInteger('RefreshSeconds', 300);
        $this->RegisterPropertyInteger('BucketMinutes', 60);

        $this->RegisterTimer(self::TIMER_REFRESH, 0, 'EDB_UpdateVisualization($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainVariable(self::IDENT_HTML, $this->ReadPropertyString('Title'), VARIABLETYPE_STRING, '~HTMLBox', 0, true);
        $this->SetTimerInterval(self::TIMER_REFRESH, max(30, $this->ReadPropertyInteger('RefreshSeconds')) * 1000);

        try {
            $this->UpdateVisualization();
            $this->SetStatus(102);
        } catch (\Throwable $e) {
            $this->SendDebug(__FUNCTION__, $e->getMessage(), 0);
            $this->SetValue(self::IDENT_HTML, $this->RenderErrorHtml($e->getMessage()));
            $this->SetStatus(201);
        }
    }

    public function UpdateVisualization(): void
    {
        $pvID = $this->ReadPropertyInteger('PvPowerID');
        $gridID = $this->ReadPropertyInteger('GridPowerID');
        $loadID = $this->ReadPropertyInteger('LoadPowerID');
        $batteryID = $this->ReadPropertyInteger('BatteryPowerID');

        if (!$this->IsValidVar($pvID) || !$this->IsValidVar($gridID) || !$this->IsValidVar($loadID)) {
            throw new Exception('Bitte mindestens PV-, Netz- und Verbrauchs-Variable konfigurieren.');
        }

        $archiveID = $this->GetArchiveId();
        if ($archiveID === 0) {
            throw new Exception('Keine Archivsteuerung gefunden.');
        }

        $start = strtotime('today');
        $end   = time();

        $pvSeries = $this->GetSeriesKw($archiveID, $pvID, $start, $end);
        $gridSeries = $this->GetSeriesKw($archiveID, $gridID, $start, $end);
        $loadSeries = $this->GetSeriesKw($archiveID, $loadID, $start, $end);
        $batterySeries = $this->IsValidVar($batteryID) ? $this->GetSeriesKw($archiveID, $batteryID, $start, $end) : [];

        $aligned = $this->AlignSeries([
            'pv'      => $pvSeries,
            'grid'    => $gridSeries,
            'load'    => $loadSeries,
            'battery' => $batterySeries
        ]);

        $bucketMinutes = max(15, $this->ReadPropertyInteger('BucketMinutes'));
        $usageBuckets = $this->BuildUsageBuckets($aligned, $bucketMinutes);

        $totals = $this->CalculateTotals($aligned);

        $payload = [
            'title'   => $this->ReadPropertyString('Title'),
            'totals'  => $totals,
            'series'  => [
                'labels'  => $aligned['labels'],
                'pv'      => $aligned['pv'],
                'grid'    => $aligned['grid'],
                'load'    => $aligned['load'],
                'battery' => $aligned['battery']
            ],
            'usage'   => $usageBuckets
        ];

        $this->SetValue(self::IDENT_HTML, $this->RenderHtml($payload));
    }

    private function IsValidVar(int $id): bool
    {
        return $id > 0 && @IPS_VariableExists($id);
    }

    private function GetArchiveId(): int
    {
        $configured = $this->ReadPropertyInteger('ArchiveControlID');
        if ($configured > 0 && @IPS_InstanceExists($configured)) {
            return $configured;
        }

        $list = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        if (count($list) > 0) {
            return $list[0];
        }
        return 0;
    }

    private function GetSeriesKw(int $archiveID, int $varID, int $start, int $end): array
    {
        $values = AC_GetLoggedValues($archiveID, $varID, $start, $end, 0);
        $result = [];

        foreach ($values as $row) {
            $ts = (int) $row['TimeStamp'];
            $value = round(((float) $row['Value']) / 1000.0, 3);
            $result[$ts] = $value;
        }

        return $result;
    }

    private function AlignSeries(array $series): array
    {
        $allTimestamps = [];
        foreach ($series as $name => $rows) {
            foreach ($rows as $ts => $value) {
                $allTimestamps[$ts] = true;
            }
        }

        ksort($allTimestamps);
        $timestamps = array_keys($allTimestamps);

        $aligned = [
            'labels'  => [],
            'pv'      => [],
            'grid'    => [],
            'load'    => [],
            'battery' => []
        ];

        $last = [
            'pv'      => 0.0,
            'grid'    => 0.0,
            'load'    => 0.0,
            'battery' => 0.0
        ];

        foreach ($timestamps as $ts) {
            $aligned['labels'][] = date('H:i', $ts);

            foreach (['pv', 'grid', 'load', 'battery'] as $name) {
                if (isset($series[$name][$ts])) {
                    $last[$name] = $series[$name][$ts];
                }
                $aligned[$name][] = round($last[$name], 3);
            }
        }

        return $aligned;
    }

    private function CalculateTotals(array $aligned): array
    {
        $pvEnergy = 0.0;
        $gridImport = 0.0;
        $gridExport = 0.0;
        $loadEnergy = 0.0;
        $batteryCharge = 0.0;
        $batteryDischarge = 0.0;

        $count = count($aligned['labels']);
        if ($count < 2) {
            return [
                'pv' => 0, 'gridImport' => 0, 'gridExport' => 0, 'load' => 0,
                'batteryCharge' => 0, 'batteryDischarge' => 0, 'netUsage' => 0
            ];
        }

        for ($i = 1; $i < $count; $i++) {
            $dtHours = $this->DeltaHours($aligned['labels'][$i - 1], $aligned['labels'][$i]);

            $pv = max(0.0, (float) $aligned['pv'][$i - 1]);
            $grid = (float) $aligned['grid'][$i - 1];
            $load = max(0.0, (float) $aligned['load'][$i - 1]);
            $battery = (float) $aligned['battery'][$i - 1];

            $pvEnergy += $pv * $dtHours;
            $loadEnergy += $load * $dtHours;

            if ($grid >= 0) {
                $gridImport += $grid * $dtHours;
            } else {
                $gridExport += abs($grid) * $dtHours;
            }

            if ($battery >= 0) {
                $batteryDischarge += $battery * $dtHours;
            } else {
                $batteryCharge += abs($battery) * $dtHours;
            }
        }

        return [
            'pv'               => round($pvEnergy, 2),
            'gridImport'       => round($gridImport, 2),
            'gridExport'       => round($gridExport, 2),
            'load'             => round($loadEnergy, 2),
            'batteryCharge'    => round($batteryCharge, 2),
            'batteryDischarge' => round($batteryDischarge, 2),
            'netUsage'         => round($loadEnergy - $gridExport, 2)
        ];
    }

    private function DeltaHours(string $from, string $to): float
    {
        $fromTs = strtotime(date('Y-m-d') . ' ' . $from . ':00');
        $toTs   = strtotime(date('Y-m-d') . ' ' . $to . ':00');

        if ($toTs <= $fromTs) {
            $toTs = $fromTs + 300;
        }

        return ($toTs - $fromTs) / 3600.0;
    }

    private function BuildUsageBuckets(array $aligned, int $bucketMinutes): array
    {
        $buckets = [];
        $count = count($aligned['labels']);
        if ($count < 2) {
            return [];
        }

        for ($i = 1; $i < $count; $i++) {
            $label = $aligned['labels'][$i - 1];
            $bucketKey = $this->BucketLabel($label, $bucketMinutes);
            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'label'           => $bucketKey,
                    'pvToLoad'        => 0.0,
                    'gridImport'      => 0.0,
                    'batteryCharge'   => 0.0,
                    'batteryDischarge'=> 0.0,
                    'gridExport'      => 0.0
                ];
            }

            $dtHours = $this->DeltaHours($aligned['labels'][$i - 1], $aligned['labels'][$i]);

            $pv = max(0.0, (float) $aligned['pv'][$i - 1]);
            $grid = (float) $aligned['grid'][$i - 1];
            $load = max(0.0, (float) $aligned['load'][$i - 1]);
            $battery = (float) $aligned['battery'][$i - 1];

            $pvToLoad = min($pv, $load);
            $gridImport = max(0.0, $grid);
            $gridExport = max(0.0, -$grid);
            $batteryDischarge = max(0.0, $battery);
            $batteryCharge = max(0.0, -$battery);

            $buckets[$bucketKey]['pvToLoad']         += $pvToLoad * $dtHours;
            $buckets[$bucketKey]['gridImport']       += $gridImport * $dtHours;
            $buckets[$bucketKey]['batteryCharge']    += $batteryCharge * $dtHours;
            $buckets[$bucketKey]['batteryDischarge'] += $batteryDischarge * $dtHours;
            $buckets[$bucketKey]['gridExport']       += $gridExport * $dtHours;
        }

        return array_values(array_map(function ($row) {
            foreach ($row as $k => $v) {
                if ($k !== 'label') {
                    $row[$k] = round((float) $v, 3);
                }
            }
            return $row;
        }, $buckets));
    }

    private function BucketLabel(string $timeLabel, int $bucketMinutes): string
    {
        [$h, $m] = explode(':', $timeLabel);
        $minutes = ((int) $h * 60) + (int) $m;
        $bucket = (int) floor($minutes / $bucketMinutes) * $bucketMinutes;
        $bh = floor($bucket / 60);
        $bm = $bucket % 60;
        return sprintf('%02d:%02d', $bh, $bm);
    }

    private function RenderErrorHtml(string $message): string
    {
        return '<div style="padding:16px;font-family:Arial,sans-serif;color:#a94442;background:#f2dede;border:1px solid #ebccd1;border-radius:8px;">'
            . htmlspecialchars($message)
            . '</div>';
    }

    private function RenderHtml(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<HTML
<div id="energy-dashboard" style="font-family: Arial, sans-serif; padding: 12px; color: #222;">
  <style>
    #energy-dashboard .card {
      background: #f7f7f7;
      border: 1px solid #d9d9d9;
      border-radius: 18px;
      padding: 16px;
      margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    #energy-dashboard .header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      margin-bottom:8px;
    }
    #energy-dashboard .title {
      font-size: 24px;
      font-weight: 700;
    }
    #energy-dashboard .badge {
      font-size: 18px;
      font-weight: 700;
      padding: 10px 14px;
      background: #fff;
      border: 1px solid #d0d0d0;
      border-radius: 14px;
    }
    #energy-dashboard .totals {
      display:grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: 8px;
      margin-bottom:12px;
    }
    #energy-dashboard .totalBox {
      background:#fff;
      border:1px solid #e1e1e1;
      border-radius:12px;
      padding:10px;
    }
    #energy-dashboard .totalLabel {
      font-size:12px;
      color:#666;
      margin-bottom:4px;
    }
    #energy-dashboard .totalValue {
      font-size:18px;
      font-weight:700;
    }
    #energy-dashboard canvas {
      width: 100% !important;
      max-width: 100%;
    }
  </style>

  <div class="card">
    <div class="header">
      <div class="title">Stromquellen</div>
      <div class="badge" id="netUsageBadge">0,00 kWh</div>
    </div>
    <div class="totals" id="totals"></div>
    <canvas id="sourceChart" height="140"></canvas>
  </div>

  <div class="card">
    <div class="header">
      <div class="title">Stromnutzung</div>
    </div>
    <canvas id="usageChart" height="150"></canvas>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
  const payload = {$json};

  function num(n) {
    return new Intl.NumberFormat('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
  }

  document.getElementById('netUsageBadge').innerText = '+' + num(payload.totals.netUsage) + ' kWh';

  const totals = [
    ['PV', payload.totals.pv],
    ['Bezug', payload.totals.gridImport],
    ['Einspeisung', payload.totals.gridExport],
    ['Verbrauch', payload.totals.load],
    ['Batt. Laden', payload.totals.batteryCharge],
    ['Batt. Entladen', payload.totals.batteryDischarge]
  ];

  const totalsEl = document.getElementById('totals');
  totals.forEach(([label, value]) => {
    const box = document.createElement('div');
    box.className = 'totalBox';
    box.innerHTML = '<div class="totalLabel">' + label + '</div><div class="totalValue">' + num(value) + ' kWh</div>';
    totalsEl.appendChild(box);
  });

  new Chart(document.getElementById('sourceChart'), {
    type: 'line',
    data: {
      labels: payload.series.labels,
      datasets: [
        {
          label: 'PV',
          data: payload.series.pv,
          borderColor: 'rgba(255, 152, 0, 1)',
          backgroundColor: 'rgba(255, 152, 0, 0.18)',
          fill: true,
          tension: 0.25,
          pointRadius: 0
        },
        {
          label: 'Netz',
          data: payload.series.grid,
          borderColor: 'rgba(0, 188, 212, 1)',
          backgroundColor: 'rgba(0, 188, 212, 0.06)',
          tension: 0.2,
          pointRadius: 0
        },
        {
          label: 'Verbrauch',
          data: payload.series.load,
          borderColor: 'rgba(0,0,0,0.85)',
          backgroundColor: 'rgba(0,0,0,0)',
          borderDash: [6, 4],
          tension: 0.2,
          pointRadius: 0
        },
        {
          label: 'Batterie',
          data: payload.series.battery,
          borderColor: 'rgba(63, 81, 181, 1)',
          backgroundColor: 'rgba(63, 81, 181, 0.06)',
          tension: 0.2,
          pointRadius: 0,
          hidden: payload.series.battery.every(v => v === 0)
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top' }
      },
      scales: {
        y: {
          title: { display: true, text: 'kW' }
        },
        x: {
          ticks: { maxTicksLimit: 8 }
        }
      }
    }
  });

  const usageLabels = payload.usage.map(x => x.label);
  const usagePvToLoad = payload.usage.map(x => x.pvToLoad);
  const usageGridImport = payload.usage.map(x => x.gridImport);
  const usageBatteryDischarge = payload.usage.map(x => x.batteryDischarge);
  const usageBatteryCharge = payload.usage.map(x => -x.batteryCharge);
  const usageGridExport = payload.usage.map(x => -x.gridExport);

  new Chart(document.getElementById('usageChart'), {
    type: 'bar',
    data: {
      labels: usageLabels,
      datasets: [
        {
          label: 'PV → Last',
          data: usagePvToLoad,
          backgroundColor: 'rgba(255, 193, 7, 0.55)',
          borderColor: 'rgba(255, 152, 0, 1)',
          borderWidth: 1,
          stack: 'energy'
        },
        {
          label: 'Netzbezug',
          data: usageGridImport,
          backgroundColor: 'rgba(128, 203, 196, 0.75)',
          borderColor: 'rgba(77, 182, 172, 1)',
          borderWidth: 1,
          stack: 'energy'
        },
        {
          label: 'Batt. Entladen',
          data: usageBatteryDischarge,
          backgroundColor: 'rgba(100, 181, 246, 0.75)',
          borderColor: 'rgba(66, 165, 245, 1)',
          borderWidth: 1,
          stack: 'energy'
        },
        {
          label: 'Batt. Laden',
          data: usageBatteryCharge,
          backgroundColor: 'rgba(244, 143, 177, 0.72)',
          borderColor: 'rgba(236, 64, 122, 1)',
          borderWidth: 1,
          stack: 'energy'
        },
        {
          label: 'Netzeinspeisung',
          data: usageGridExport,
          backgroundColor: 'rgba(179, 157, 219, 0.72)',
          borderColor: 'rgba(126, 87, 194, 1)',
          borderWidth: 1,
          stack: 'energy'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top' }
      },
      scales: {
        y: {
          title: { display: true, text: 'kWh' },
          stacked: true
        },
        x: {
          stacked: true
        }
      }
    }
  });
})();
</script>
HTML;
    }
}
