<?php

declare(strict_types=1);

class EnergyDashboard extends IPSModule
{
    private const IDENT_OVERVIEW = 'OverviewHTML';
    private const IDENT_SOURCES  = 'SourcesHTML';
    private const IDENT_USAGE    = 'UsageHTML';
    private const TIMER_REFRESH  = 'Refresh';

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Title', 'Energie Dashboard');

        $this->RegisterPropertyInteger('PvPowerID', 0);
        $this->RegisterPropertyBoolean('InvertPv', false);

        $this->RegisterPropertyInteger('GridPowerID', 0);
        $this->RegisterPropertyBoolean('InvertGrid', false);

        $this->RegisterPropertyInteger('LoadPowerID', 0);
        $this->RegisterPropertyBoolean('InvertLoad', false);

        $this->RegisterPropertyInteger('BatteryPowerID', 0);
        $this->RegisterPropertyBoolean('InvertBattery', false);

        $this->RegisterPropertyInteger('PvEnergyTodayID', 0);
        $this->RegisterPropertyInteger('GridImportTodayID', 0);
        $this->RegisterPropertyInteger('GridExportTodayID', 0);
        $this->RegisterPropertyInteger('LoadEnergyTodayID', 0);
        $this->RegisterPropertyInteger('BatteryChargeTodayID', 0);
        $this->RegisterPropertyInteger('BatteryDischargeTodayID', 0);

        $this->RegisterPropertyInteger('ArchiveControlID', 0);
        $this->RegisterPropertyInteger('SourceAggregation', 5);
        $this->RegisterPropertyInteger('UsageAggregation', 0);
        $this->RegisterPropertyInteger('RefreshSeconds', 300);
        $this->RegisterPropertyInteger('MaxSourcePoints', 180);
        $this->RegisterPropertyInteger('MaxUsagePoints', 24);

        $this->RegisterTimer(self::TIMER_REFRESH, 0, 'EDB_UpdateVisualization($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainVariable(self::IDENT_OVERVIEW, 'Verbrauchsübersicht', VARIABLETYPE_STRING, '~HTMLBox', 0, true);
        $this->MaintainVariable(self::IDENT_SOURCES, 'Stromquellen', VARIABLETYPE_STRING, '~HTMLBox', 1, true);
        $this->MaintainVariable(self::IDENT_USAGE, 'Stromnutzung', VARIABLETYPE_STRING, '~HTMLBox', 2, true);

        $this->SetTimerInterval(self::TIMER_REFRESH, max(30, $this->ReadPropertyInteger('RefreshSeconds')) * 1000);

        try {
            $this->UpdateVisualization();
            $this->SetStatus(102);
        } catch (\Throwable $e) {
            $error = $this->RenderErrorHtml($e->getMessage());
            @SetValue($this->GetIDForIdent(self::IDENT_OVERVIEW), $error);
            @SetValue($this->GetIDForIdent(self::IDENT_SOURCES), $error);
            @SetValue($this->GetIDForIdent(self::IDENT_USAGE), $error);
            $this->SendDebug(__FUNCTION__, $e->getMessage(), 0);
            $this->SetStatus(201);
        }
    }

    public function UpdateVisualization(): void
    {
        $pvID      = $this->ReadPropertyInteger('PvPowerID');
        $gridID    = $this->ReadPropertyInteger('GridPowerID');
        $loadID    = $this->ReadPropertyInteger('LoadPowerID');

        if (!$this->IsValidVar($pvID) || !$this->IsValidVar($gridID) || !$this->IsValidVar($loadID)) {
            throw new Exception('Bitte mindestens PV-, Netz- und Verbrauchs-Variable konfigurieren.');
        }

        $archiveID = $this->GetArchiveId();
        if ($archiveID === 0) {
            throw new Exception('Keine Archivsteuerung gefunden.');
        }

        $start = strtotime('today');
        $end   = time();

        $sourceChart = $this->BuildSourceChartData($archiveID, $start, $end);
        $usageChart  = $this->BuildUsageChartData($archiveID, $start, $end);
        $totals      = $this->CalculateTotalsFromSourceData($sourceChart);
        $totals      = $this->OverrideTotalsWithTodayCounters($totals);

        SetValue($this->GetIDForIdent(self::IDENT_OVERVIEW), $this->GetOverviewHtml($totals));
        SetValue($this->GetIDForIdent(self::IDENT_SOURCES), $this->GetSourcesHtml($sourceChart));
        SetValue($this->GetIDForIdent(self::IDENT_USAGE), $this->GetUsageHtml($usageChart));
    }

    private function IsValidVar(int $id): bool
    {
        return $id > 0 && @IPS_VariableExists($id);
    }

    private function ApplySign(float $value, bool $invert): float
    {
        return $invert ? -$value : $value;
    }

    private function GetArchiveId(): int
    {
        $configured = $this->ReadPropertyInteger('ArchiveControlID');
        if ($configured > 0 && @IPS_InstanceExists($configured)) {
            return $configured;
        }

        $list = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        return count($list) > 0 ? $list[0] : 0;
    }

    private function GetAggregatedSeriesKw(int $archiveID, int $varID, int $aggregation, int $start, int $end, bool $invert): array
    {
        if (!$this->IsValidVar($varID)) {
            return [];
        }

        $rows = @AC_GetAggregatedValues($archiveID, $varID, $aggregation, $start, $end, 0);
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $ts = (int) $row['TimeStamp'];
            if (isset($row['Avg'])) {
                $value = (float) $row['Avg'];
            } elseif (isset($row['Value'])) {
                $value = (float) $row['Value'];
            } else {
                continue;
            }

            $value = $this->ApplySign($value, $invert);
            $result[$ts] = round($value / 1000.0, 3);
        }

        ksort($result);
        return $result;
    }

    private function BuildSourceChartData(int $archiveID, int $start, int $end): array
    {
        $aggregation = $this->ReadPropertyInteger('SourceAggregation');

        $pvRows = $this->GetAggregatedSeriesKw(
            $archiveID,
            $this->ReadPropertyInteger('PvPowerID'),
            $aggregation,
            $start,
            $end,
            $this->ReadPropertyBoolean('InvertPv')
        );

        $gridRows = $this->GetAggregatedSeriesKw(
            $archiveID,
            $this->ReadPropertyInteger('GridPowerID'),
            $aggregation,
            $start,
            $end,
            $this->ReadPropertyBoolean('InvertGrid')
        );

        $loadRows = $this->GetAggregatedSeriesKw(
            $archiveID,
            $this->ReadPropertyInteger('LoadPowerID'),
            $aggregation,
            $start,
            $end,
            $this->ReadPropertyBoolean('InvertLoad')
        );

        $batteryRows = $this->GetAggregatedSeriesKw(
            $archiveID,
            $this->ReadPropertyInteger('BatteryPowerID'),
            $aggregation,
            $start,
            $end,
            $this->ReadPropertyBoolean('InvertBattery')
        );

        $aligned = $this->AlignSeriesByTimestamp([
            'pv'      => $pvRows,
            'grid'    => $gridRows,
            'load'    => $loadRows,
            'battery' => $batteryRows
        ]);

        return $this->ReduceAlignedSeries($aligned, max(24, $this->ReadPropertyInteger('MaxSourcePoints')));
    }

    private function BuildUsageChartData(int $archiveID, int $start, int $end): array
    {
        $aggregation = $this->ReadPropertyInteger('UsageAggregation');

        $pvRows = $this->GetAggregatedSeriesKw(
            $archiveID,
            $this->ReadPropertyInteger('PvPowerID'),
            $aggregation,
            $start,
            $end,
            $this->ReadPropertyBoolean('InvertPv')
        );

        $gridRows = $this->GetAggregatedSeriesKw(
            $archiveID,
            $this->ReadPropertyInteger('GridPowerID'),
            $aggregation,
            $start,
            $end,
            $this->ReadPropertyBoolean('InvertGrid')
        );

        $loadRows = $this->GetAggregatedSeriesKw(
            $archiveID,
            $this->ReadPropertyInteger('LoadPowerID'),
            $aggregation,
            $start,
            $end,
            $this->ReadPropertyBoolean('InvertLoad')
        );

        $batteryRows = $this->GetAggregatedSeriesKw(
            $archiveID,
            $this->ReadPropertyInteger('BatteryPowerID'),
            $aggregation,
            $start,
            $end,
            $this->ReadPropertyBoolean('InvertBattery')
        );

        $aligned = $this->AlignSeriesByTimestamp([
            'pv'      => $pvRows,
            'grid'    => $gridRows,
            'load'    => $loadRows,
            'battery' => $batteryRows
        ]);

        $buckets = [];
        $count = count($aligned['timestamps']);
        if ($count < 2) {
            return [];
        }

        for ($i = 1; $i < $count; $i++) {
            $from = (int) $aligned['timestamps'][$i - 1];
            $to   = (int) $aligned['timestamps'][$i];
            $dtHours = max(1 / 60, ($to - $from) / 3600.0);

            $pv      = max(0.0, (float) $aligned['pv'][$i - 1]);
            $grid    = (float) $aligned['grid'][$i - 1];
            $load    = max(0.0, (float) $aligned['load'][$i - 1]);
            $battery = (float) $aligned['battery'][$i - 1];

            $buckets[] = [
                'label'            => date('H:i', $from),
                'pvToLoad'         => round(min($pv, $load) * $dtHours, 3),
                'gridImport'       => round(max(0.0, $grid) * $dtHours, 3),
                'batteryCharge'    => round(max(0.0, -$battery) * $dtHours, 3),
                'batteryDischarge' => round(max(0.0, $battery) * $dtHours, 3),
                'gridExport'       => round(max(0.0, -$grid) * $dtHours, 3)
            ];
        }

        return $this->ReduceUsageBuckets($buckets, max(8, $this->ReadPropertyInteger('MaxUsagePoints')));
    }

    private function AlignSeriesByTimestamp(array $series): array
    {
        $allTimestamps = [];
        foreach ($series as $rows) {
            foreach ($rows as $ts => $value) {
                $allTimestamps[$ts] = true;
            }
        }

        ksort($allTimestamps);
        $timestamps = array_keys($allTimestamps);

        $aligned = [
            'timestamps' => [],
            'labels'     => [],
            'pv'         => [],
            'grid'       => [],
            'load'       => [],
            'battery'    => []
        ];

        $last = [
            'pv'      => 0.0,
            'grid'    => 0.0,
            'load'    => 0.0,
            'battery' => 0.0
        ];

        foreach ($timestamps as $ts) {
            $aligned['timestamps'][] = $ts;
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

    private function ReduceAlignedSeries(array $aligned, int $maxPoints): array
    {
        $count = count($aligned['labels']);
        if ($count <= $maxPoints) {
            return $aligned;
        }

        $step = (int) ceil($count / $maxPoints);
        $reduced = [
            'timestamps' => [],
            'labels'     => [],
            'pv'         => [],
            'grid'       => [],
            'load'       => [],
            'battery'    => []
        ];

        for ($i = 0; $i < $count; $i += $step) {
            $sliceEnd = min($i + $step, $count);
            $reduced['timestamps'][] = $aligned['timestamps'][$sliceEnd - 1];
            $reduced['labels'][]     = $aligned['labels'][$sliceEnd - 1];
            $reduced['pv'][]         = round($this->AverageSlice($aligned['pv'], $i, $sliceEnd), 3);
            $reduced['grid'][]       = round($this->AverageSlice($aligned['grid'], $i, $sliceEnd), 3);
            $reduced['load'][]       = round($this->AverageSlice($aligned['load'], $i, $sliceEnd), 3);
            $reduced['battery'][]    = round($this->AverageSlice($aligned['battery'], $i, $sliceEnd), 3);
        }

        return $reduced;
    }

    private function ReduceUsageBuckets(array $rows, int $maxPoints): array
    {
        $count = count($rows);
        if ($count <= $maxPoints) {
            return $rows;
        }

        $step = (int) ceil($count / $maxPoints);
        $reduced = [];

        for ($i = 0; $i < $count; $i += $step) {
            $slice = array_slice($rows, $i, $step);
            $last = $slice[count($slice) - 1];

            $reduced[] = [
                'label'            => $last['label'],
                'pvToLoad'         => round($this->SumColumn($slice, 'pvToLoad'), 3),
                'gridImport'       => round($this->SumColumn($slice, 'gridImport'), 3),
                'batteryCharge'    => round($this->SumColumn($slice, 'batteryCharge'), 3),
                'batteryDischarge' => round($this->SumColumn($slice, 'batteryDischarge'), 3),
                'gridExport'       => round($this->SumColumn($slice, 'gridExport'), 3)
            ];
        }

        return $reduced;
    }

    private function CalculateTotalsFromSourceData(array $sourceChart): array
    {
        $pvEnergy = 0.0;
        $gridImport = 0.0;
        $gridExport = 0.0;
        $loadEnergy = 0.0;
        $batteryCharge = 0.0;
        $batteryDischarge = 0.0;

        $count = count($sourceChart['timestamps']);
        if ($count < 2) {
            return [
                'pv' => 0.0,
                'gridImport' => 0.0,
                'gridExport' => 0.0,
                'load' => 0.0,
                'batteryCharge' => 0.0,
                'batteryDischarge' => 0.0,
                'netUsage' => 0.0,
                'selfConsumption' => 0.0,
                'autarky' => 0.0
            ];
        }

        for ($i = 1; $i < $count; $i++) {
            $from = (int) $sourceChart['timestamps'][$i - 1];
            $to   = (int) $sourceChart['timestamps'][$i];
            $dtHours = max(1 / 60, ($to - $from) / 3600.0);

            $pv      = max(0.0, (float) $sourceChart['pv'][$i - 1]);
            $grid    = (float) $sourceChart['grid'][$i - 1];
            $load    = max(0.0, (float) $sourceChart['load'][$i - 1]);
            $battery = (float) $sourceChart['battery'][$i - 1];

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

        $selfConsumption = max(0.0, $pvEnergy - $gridExport);
        $autarky = $loadEnergy > 0
            ? min(100.0, max(0.0, (($loadEnergy - $gridImport) / $loadEnergy) * 100.0))
            : 0.0;

        return [
            'pv'               => round($pvEnergy, 2),
            'gridImport'       => round($gridImport, 2),
            'gridExport'       => round($gridExport, 2),
            'load'             => round($loadEnergy, 2),
            'batteryCharge'    => round($batteryCharge, 2),
            'batteryDischarge' => round($batteryDischarge, 2),
            'netUsage'         => round($loadEnergy - $gridExport, 2),
            'selfConsumption'  => round($selfConsumption, 2),
            'autarky'          => round($autarky, 1)
        ];
    }

    private function OverrideTotalsWithTodayCounters(array $totals): array
    {
        $map = [
            'pv'               => 'PvEnergyTodayID',
            'gridImport'       => 'GridImportTodayID',
            'gridExport'       => 'GridExportTodayID',
            'load'             => 'LoadEnergyTodayID',
            'batteryCharge'    => 'BatteryChargeTodayID',
            'batteryDischarge' => 'BatteryDischargeTodayID'
        ];

        foreach ($map as $key => $property) {
            $id = $this->ReadPropertyInteger($property);
            if ($this->IsValidVar($id)) {
                $totals[$key] = round((float) GetValue($id), 2);
            }
        }

        $totals['selfConsumption'] = round(max(0.0, $totals['pv'] - $totals['gridExport']), 2);
        $totals['netUsage'] = round($totals['load'] - $totals['gridExport'], 2);
        $totals['autarky'] = $totals['load'] > 0
            ? round(min(100.0, max(0.0, (($totals['load'] - $totals['gridImport']) / $totals['load']) * 100.0)), 1)
            : 0.0;

        return $totals;
    }

    private function SumColumn(array $rows, string $column): float
    {
        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) $row[$column];
        }
        return $sum;
    }

    private function AverageSlice(array $values, int $start, int $end): float
    {
        $slice = array_slice($values, $start, $end - $start);
        return count($slice) === 0 ? 0.0 : array_sum($slice) / count($slice);
    }

    private function GetOverviewHtml(array $t): string
    {
        return <<<HTML
<div style="font-family:Arial,sans-serif;padding:12px;color:#222;">
  <style>
    .edb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
    .edb-card{background:#f7f7f7;border:1px solid #d9d9d9;border-radius:18px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .edb-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
    .edb-title{font-size:24px;font-weight:700}
    .edb-badge{font-size:18px;font-weight:700;padding:10px 14px;background:#fff;border:1px solid #d0d0d0;border-radius:14px}
    .edb-box{background:#fff;border:1px solid #e1e1e1;border-radius:12px;padding:12px}
    .edb-label{font-size:12px;color:#666;margin-bottom:4px}
    .edb-value{font-size:20px;font-weight:700}
  </style>
  <div class="edb-card">
    <div class="edb-head">
      <div class="edb-title">Verbrauchsübersicht</div>
      <div class="edb-badge">+{$this->Fmt($t['netUsage'])} kWh</div>
    </div>
    <div class="edb-grid">
      {$this->OverviewBox('PV', $this->Fmt($t['pv']) . ' kWh')}
      {$this->OverviewBox('Bezug', $this->Fmt($t['gridImport']) . ' kWh')}
      {$this->OverviewBox('Einspeisung', $this->Fmt($t['gridExport']) . ' kWh')}
      {$this->OverviewBox('Verbrauch', $this->Fmt($t['load']) . ' kWh')}
      {$this->OverviewBox('Batt. Laden', $this->Fmt($t['batteryCharge']) . ' kWh')}
      {$this->OverviewBox('Batt. Entladen', $this->Fmt($t['batteryDischarge']) . ' kWh')}
      {$this->OverviewBox('Eigenverbrauch', $this->Fmt($t['selfConsumption']) . ' kWh')}
      {$this->OverviewBox('Autarkie', $this->Fmt($t['autarky']) . ' %')}
    </div>
  </div>
</div>
HTML;
    }

    private function OverviewBox(string $label, string $value): string
    {
        return '<div class="edb-box"><div class="edb-label">' . htmlspecialchars($label) . '</div><div class="edb-value">' . htmlspecialchars($value) . '</div></div>';
    }

    private function GetSourcesHtml(array $data): string
    {
        $json = json_encode([
            'labels'  => $data['labels'],
            'pv'      => $data['pv'],
            'grid'    => $data['grid'],
            'load'    => $data['load'],
            'battery' => $data['battery']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $height = max(220, min(420, 220 + (int) floor(count($data['labels']) / 4)));

        return <<<HTML
<div style="font-family:Arial,sans-serif;padding:12px;color:#222;">
  <style>
    .edb-card{background:#f7f7f7;border:1px solid #d9d9d9;border-radius:18px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .edb-title{font-size:24px;font-weight:700;margin-bottom:8px}
    .edb-wrap{position:relative;height:{$height}px}
  </style>
  <div class="edb-card">
    <div class="edb-title">Stromquellen</div>
    <div class="edb-wrap"><canvas id="edbSourceChart"></canvas></div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    (function(){
      const d = $json;
      new Chart(document.getElementById('edbSourceChart'), {
        type: 'line',
        data: {
          labels: d.labels,
          datasets: [
            {label:'PV', data:d.pv, borderColor:'rgba(255,152,0,1)', backgroundColor:'rgba(255,152,0,.18)', fill:true, tension:.25, pointRadius:0},
            {label:'Netz', data:d.grid, borderColor:'rgba(0,188,212,1)', backgroundColor:'rgba(0,188,212,.06)', tension:.2, pointRadius:0},
            {label:'Verbrauch', data:d.load, borderColor:'rgba(0,0,0,.85)', backgroundColor:'rgba(0,0,0,0)', borderDash:[6,4], tension:.2, pointRadius:0},
            {label:'Batterie', data:d.battery, borderColor:'rgba(63,81,181,1)', backgroundColor:'rgba(63,81,181,.06)', tension:.2, pointRadius:0, hidden:d.battery.every(v => v === 0)}
          ]
        },
        options: {
          responsive:true,
          maintainAspectRatio:false,
          animation:false,
          interaction:{mode:'index', intersect:false},
          plugins:{legend:{position:'top'}},
          scales:{
            y:{title:{display:true,text:'kW'}},
            x:{ticks:{maxTicksLimit:8}}
          }
        }
      });
    })();
  </script>
</div>
HTML;
    }

    private function GetUsageHtml(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $height = max(220, min(460, 220 + (int) floor(count($data) * 5)));

        return <<<HTML
<div style="font-family:Arial,sans-serif;padding:12px;color:#222;">
  <style>
    .edb-card{background:#f7f7f7;border:1px solid #d9d9d9;border-radius:18px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .edb-title{font-size:24px;font-weight:700;margin-bottom:8px}
    .edb-wrap{position:relative;height:{$height}px}
  </style>
  <div class="edb-card">
    <div class="edb-title">Stromnutzung</div>
    <div class="edb-wrap"><canvas id="edbUsageChart"></canvas></div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    (function(){
      const d = $json;
      new Chart(document.getElementById('edbUsageChart'), {
        type: 'bar',
        data: {
          labels: d.map(x => x.label),
          datasets: [
            {label:'PV → Last', data:d.map(x => x.pvToLoad), backgroundColor:'rgba(255,193,7,.55)', borderColor:'rgba(255,152,0,1)', borderWidth:1, stack:'energy'},
            {label:'Netzbezug', data:d.map(x => x.gridImport), backgroundColor:'rgba(128,203,196,.75)', borderColor:'rgba(77,182,172,1)', borderWidth:1, stack:'energy'},
            {label:'Batt. Entladen', data:d.map(x => x.batteryDischarge), backgroundColor:'rgba(100,181,246,.75)', borderColor:'rgba(66,165,245,1)', borderWidth:1, stack:'energy'},
            {label:'Batt. Laden', data:d.map(x => -x.batteryCharge), backgroundColor:'rgba(244,143,177,.72)', borderColor:'rgba(236,64,122,1)', borderWidth:1, stack:'energy'},
            {label:'Netzeinspeisung', data:d.map(x => -x.gridExport), backgroundColor:'rgba(179,157,219,.72)', borderColor:'rgba(126,87,194,1)', borderWidth:1, stack:'energy'}
          ]
        },
        options: {
          responsive:true,
          maintainAspectRatio:false,
          animation:false,
          interaction:{mode:'index', intersect:false},
          plugins:{legend:{position:'top'}},
          scales:{
            y:{title:{display:true,text:'kWh'}, stacked:true},
            x:{stacked:true}
          }
        }
      });
    })();
  </script>
</div>
HTML;
    }

    private function Fmt(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function RenderErrorHtml(string $message): string
    {
        return '<div style="padding:16px;font-family:Arial,sans-serif;color:#a94442;background:#f2dede;border:1px solid #ebccd1;border-radius:8px;">'
            . htmlspecialchars($message)
            . '</div>';
    }
}
