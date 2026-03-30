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
        $this->RegisterPropertyInteger('GridPowerID', 0);
        $this->RegisterPropertyInteger('LoadPowerID', 0);
        $this->RegisterPropertyInteger('BatteryPowerID', 0);
        $this->RegisterPropertyInteger('ArchiveControlID', 0);
        $this->RegisterPropertyInteger('RefreshSeconds', 300);
        $this->RegisterPropertyInteger('BucketMinutes', 60);
        $this->RegisterPropertyInteger('MaxSourcePoints', 180);

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
            $errorHtml = $this->RenderErrorHtml($e->getMessage());
            $this->SetValue(self::IDENT_OVERVIEW, $errorHtml);
            $this->SetValue(self::IDENT_SOURCES, $errorHtml);
            $this->SetValue(self::IDENT_USAGE, $errorHtml);
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

        $maxSourcePoints = max(48, $this->ReadPropertyInteger('MaxSourcePoints'));
        $sourceChart = $this->ReduceAlignedSeries($aligned, $maxSourcePoints);

        $this->SetValue(self::IDENT_OVERVIEW, $this->RenderTemplate('assets/verbrauchsuebersicht.html', $totals));
        $this->SetValue(self::IDENT_SOURCES, $this->RenderTemplate('assets/stromquellen.html', [
            'labels'  => $sourceChart['labels'],
            'pv'      => $sourceChart['pv'],
            'grid'    => $sourceChart['grid'],
            'load'    => $sourceChart['load'],
            'battery' => $sourceChart['battery']
        ]));
        $this->SetValue(self::IDENT_USAGE, $this->RenderTemplate('assets/stromnutzung.html', $usageBuckets));
    }

    private function RenderTemplate(string $relativePath, $payload): string
    {
        $path = __DIR__ . '/' . $relativePath;
        if (!file_exists($path)) {
            throw new Exception('Template nicht gefunden: ' . $relativePath);
        }

        $html = file_get_contents($path);
        if ($html === false) {
            throw new Exception('Template konnte nicht gelesen werden: ' . $relativePath);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return str_replace('PAYLOAD_JSON', $json, $html);
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
        return count($list) > 0 ? $list[0] : 0;
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
        foreach ($series as $rows) {
            foreach ($rows as $ts => $value) {
                $allTimestamps[$ts] = true;
            }
        }

        ksort($allTimestamps);
        $timestamps = array_keys($allTimestamps);

        $aligned = ['labels' => [], 'pv' => [], 'grid' => [], 'load' => [], 'battery' => []];
        $last = ['pv' => 0.0, 'grid' => 0.0, 'load' => 0.0, 'battery' => 0.0];

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

    private function ReduceAlignedSeries(array $aligned, int $maxPoints): array
    {
        $count = count($aligned['labels']);
        if ($count <= $maxPoints) {
            return $aligned;
        }

        $step = (int) ceil($count / $maxPoints);
        $reduced = ['labels' => [], 'pv' => [], 'grid' => [], 'load' => [], 'battery' => []];

        for ($i = 0; $i < $count; $i += $step) {
            $sliceEnd = min($i + $step, $count);
            $reduced['labels'][] = $aligned['labels'][$sliceEnd - 1];
            $reduced['pv'][] = round($this->AverageSlice($aligned['pv'], $i, $sliceEnd), 3);
            $reduced['grid'][] = round($this->AverageSlice($aligned['grid'], $i, $sliceEnd), 3);
            $reduced['load'][] = round($this->AverageSlice($aligned['load'], $i, $sliceEnd), 3);
            $reduced['battery'][] = round($this->AverageSlice($aligned['battery'], $i, $sliceEnd), 3);
        }

        return $reduced;
    }

    private function AverageSlice(array $values, int $start, int $end): float
    {
        $slice = array_slice($values, $start, $end - $start);
        return count($slice) === 0 ? 0.0 : (array_sum($slice) / count($slice));
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
                'batteryCharge' => 0, 'batteryDischarge' => 0, 'netUsage' => 0,
                'selfConsumption' => 0, 'autarky' => 0
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

        $selfConsumption = max(0.0, $pvEnergy - $gridExport);
        $autarky = $loadEnergy > 0 ? min(100.0, max(0.0, (($loadEnergy - $gridImport) / $loadEnergy) * 100.0)) : 0.0;

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
                    'label' => $bucketKey,
                    'pvToLoad' => 0.0,
                    'gridImport' => 0.0,
                    'batteryCharge' => 0.0,
                    'batteryDischarge' => 0.0,
                    'gridExport' => 0.0
                ];
            }

            $dtHours = $this->DeltaHours($aligned['labels'][$i - 1], $aligned['labels'][$i]);

            $pv = max(0.0, (float) $aligned['pv'][$i - 1]);
            $grid = (float) $aligned['grid'][$i - 1];
            $load = max(0.0, (float) $aligned['load'][$i - 1]);
            $battery = (float) $aligned['battery'][$i - 1];

            $buckets[$bucketKey]['pvToLoad'] += min($pv, $load) * $dtHours;
            $buckets[$bucketKey]['gridImport'] += max(0.0, $grid) * $dtHours;
            $buckets[$bucketKey]['batteryCharge'] += max(0.0, -$battery) * $dtHours;
            $buckets[$bucketKey]['batteryDischarge'] += max(0.0, $battery) * $dtHours;
            $buckets[$bucketKey]['gridExport'] += max(0.0, -$grid) * $dtHours;
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
        return sprintf('%02d:%02d', floor($bucket / 60), $bucket % 60);
    }

    private function RenderErrorHtml(string $message): string
    {
        return '<div style="padding:16px;font-family:Arial,sans-serif;color:#a94442;background:#f2dede;border:1px solid #ebccd1;border-radius:8px;">'
            . htmlspecialchars($message)
            . '</div>';
    }
}
