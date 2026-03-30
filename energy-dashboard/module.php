<?php

declare(strict_types=1);

class EnergyDashboard extends IPSModule
{
    private const IDENT_OVERVIEW = 'OverviewHTML';
    private const IDENT_SOURCES  = 'SourcesHTML';
    private const IDENT_USAGE    = 'UsageHTML';
    private const IDENT_NAV      = 'NavigationHTML';

    private const IDENT_PERIOD_MODE = 'WF_PeriodMode';
    private const IDENT_REFERENCE_DATE = 'WF_ReferenceDate';
    private const IDENT_ACTION_PREV = 'WF_ActionPrev';
    private const IDENT_ACTION_TODAY = 'WF_ActionToday';
    private const IDENT_ACTION_NEXT = 'WF_ActionNext';

    private const TIMER_REFRESH  = 'Refresh';

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Title', 'Energie Dashboard');
        $this->RegisterPropertyBoolean('CreateWebFrontControls', true);

        $this->RegisterPropertyInteger('PvPowerID', 0);
        $this->RegisterPropertyBoolean('InvertPv', false);
        $this->RegisterPropertyInteger('GridPowerID', 0);
        $this->RegisterPropertyBoolean('InvertGrid', false);
        $this->RegisterPropertyInteger('LoadPowerID', 0);
        $this->RegisterPropertyBoolean('InvertLoad', false);
        $this->RegisterPropertyInteger('BatteryPowerID', 0);
        $this->RegisterPropertyBoolean('InvertBattery', false);

        $this->RegisterPropertyBoolean('UseHistoricalDayEnergy', true);
        $this->RegisterPropertyInteger('PvEnergyDayID', 0);
        $this->RegisterPropertyInteger('GridImportEnergyDayID', 0);
        $this->RegisterPropertyInteger('GridExportEnergyDayID', 0);
        $this->RegisterPropertyInteger('LoadEnergyDayID', 0);
        $this->RegisterPropertyInteger('BatteryChargeEnergyDayID', 0);
        $this->RegisterPropertyInteger('BatteryDischargeEnergyDayID', 0);

        $this->RegisterPropertyBoolean('UseHistoricalCounterDiff', true);
        $this->RegisterPropertyInteger('PvEnergyTotalID', 0);
        $this->RegisterPropertyInteger('GridImportEnergyTotalID', 0);
        $this->RegisterPropertyInteger('GridExportEnergyTotalID', 0);
        $this->RegisterPropertyInteger('LoadEnergyTotalID', 0);
        $this->RegisterPropertyInteger('BatteryChargeEnergyTotalID', 0);
        $this->RegisterPropertyInteger('BatteryDischargeEnergyTotalID', 0);

        $this->RegisterPropertyInteger('ArchiveControlID', 0);
        $this->RegisterPropertyInteger('SourceAggregation', 5);
        $this->RegisterPropertyInteger('UsageAggregation', 0);
        $this->RegisterPropertyInteger('RefreshSeconds', 300);
        $this->RegisterPropertyInteger('MaxSourcePoints', 180);
        $this->RegisterPropertyInteger('MaxUsagePoints', 24);

        $this->RegisterAttributeString('PeriodMode', 'day');
        $this->RegisterAttributeInteger('ReferenceTimestamp', 0);

        $this->RegisterTimer(self::TIMER_REFRESH, 0, 'EDB_UpdateVisualization($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainVariable(self::IDENT_OVERVIEW, 'Verbrauchsübersicht', VARIABLETYPE_STRING, '~HTMLBox', 0, true);
        $this->MaintainVariable(self::IDENT_SOURCES, 'Stromquellen', VARIABLETYPE_STRING, '~HTMLBox', 1, true);
        $this->MaintainVariable(self::IDENT_USAGE, 'Stromnutzung', VARIABLETYPE_STRING, '~HTMLBox', 2, true);
        $this->MaintainVariable(self::IDENT_NAV, 'Zeitraum', VARIABLETYPE_STRING, '~HTMLBox', 3, true);

        if ($this->ReadPropertyBoolean('CreateWebFrontControls')) {
            $this->EnsureWebFrontControls();
        } else {
            $this->MaintainVariable(self::IDENT_PERIOD_MODE, 'Intervall', VARIABLETYPE_INTEGER, '', 10, false);
            $this->MaintainVariable(self::IDENT_REFERENCE_DATE, 'Referenzdatum', VARIABLETYPE_INTEGER, '~UnixTimestampDate', 11, false);
            $this->MaintainVariable(self::IDENT_ACTION_PREV, 'Zurück', VARIABLETYPE_BOOLEAN, '~Switch', 12, false);
            $this->MaintainVariable(self::IDENT_ACTION_TODAY, 'Heute', VARIABLETYPE_BOOLEAN, '~Switch', 13, false);
            $this->MaintainVariable(self::IDENT_ACTION_NEXT, 'Vor', VARIABLETYPE_BOOLEAN, '~Switch', 14, false);
        }

        if ($this->ReadAttributeInteger('ReferenceTimestamp') <= 0) {
            $this->WriteAttributeInteger('ReferenceTimestamp', time());
        }

        $this->SetTimerInterval(self::TIMER_REFRESH, max(30, $this->ReadPropertyInteger('RefreshSeconds')) * 1000);

        try {
            $this->SyncControlsFromAttributes();
            $this->UpdateVisualization();
            $this->SetStatus(102);
        } catch (\Throwable $e) {
            $error = $this->RenderErrorHtml($e->getMessage());
            @SetValue($this->GetIDForIdent(self::IDENT_OVERVIEW), $error);
            @SetValue($this->GetIDForIdent(self::IDENT_SOURCES), $error);
            @SetValue($this->GetIDForIdent(self::IDENT_USAGE), $error);
            @SetValue($this->GetIDForIdent(self::IDENT_NAV), $error);
            $this->SendDebug(__FUNCTION__, $e->getMessage(), 0);
            $this->SetStatus(201);
        }
    }

    public function EnsureWebFrontControls(): void
    {
        $this->RegisterProfiles();

        $this->MaintainVariable(self::IDENT_PERIOD_MODE, 'Intervall', VARIABLETYPE_INTEGER, 'EDB.PeriodMode', 10, true);
        $this->EnableAction(self::IDENT_PERIOD_MODE);

        $this->MaintainVariable(self::IDENT_REFERENCE_DATE, 'Referenzdatum', VARIABLETYPE_INTEGER, '~UnixTimestampDate', 11, true);
        $this->EnableAction(self::IDENT_REFERENCE_DATE);

        $this->MaintainVariable(self::IDENT_ACTION_PREV, 'Zurück', VARIABLETYPE_INTEGER, 'EDB.TriggerPrev', 12, true);
        $this->EnableAction(self::IDENT_ACTION_PREV);

        $this->MaintainVariable(self::IDENT_ACTION_TODAY, 'Heute', VARIABLETYPE_INTEGER, 'EDB.TriggerToday', 13, true);
        $this->EnableAction(self::IDENT_ACTION_TODAY);

        $this->MaintainVariable(self::IDENT_ACTION_NEXT, 'Vor', VARIABLETYPE_INTEGER, 'EDB.TriggerNext', 14, true);
        $this->EnableAction(self::IDENT_ACTION_NEXT);

        $this->SyncControlsFromAttributes();
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case self::IDENT_PERIOD_MODE:
                $modeMap = [0 => 'day', 1 => 'week', 2 => 'month', 3 => 'year'];
                $mode = $modeMap[(int) $Value] ?? 'day';
                $this->WriteAttributeString('PeriodMode', $mode);
                $this->UpdateVisualization();
                break;

            case self::IDENT_REFERENCE_DATE:
                $ts = strtotime(date('Y-m-d 12:00:00', (int) $Value));
                if ($ts === false) {
                    $ts = time();
                }
                $old = $this->ReadAttributeInteger('ReferenceTimestamp');
                $hour = (int) date('H', $old);
                $minute = (int) date('i', $old);
                $second = (int) date('s', $old);
                $ts = mktime($hour, $minute, $second, (int) date('n', $ts), (int) date('j', $ts), (int) date('Y', $ts));
                $this->WriteAttributeInteger('ReferenceTimestamp', $ts);
                $this->UpdateVisualization();
                break;

            case self::IDENT_ACTION_PREV:
                if ((int) $Value === 1) {
                    $this->ShiftPeriod(-1);
                }
                @SetValue($this->GetIDForIdent(self::IDENT_ACTION_PREV), 0);
                break;

            case self::IDENT_ACTION_TODAY:
                if ((int) $Value === 1) {
                    $this->GoToToday();
                }
                @SetValue($this->GetIDForIdent(self::IDENT_ACTION_TODAY), 0);
                break;

            case self::IDENT_ACTION_NEXT:
                if ((int) $Value === 1) {
                    $this->ShiftPeriod(1);
                }
                @SetValue($this->GetIDForIdent(self::IDENT_ACTION_NEXT), 0);
                break;

            default:
                throw new Exception('Ungültiger Ident');
        }
    }

    public function GoToToday(): void
    {
        $this->WriteAttributeInteger('ReferenceTimestamp', time());
        $this->WriteAttributeString('PeriodMode', $this->ReadAttributeString('PeriodMode'));
        $this->SyncControlsFromAttributes();
        $this->UpdateVisualization();
    }

    public function ShiftPeriod(int $direction): void
    {
        $reference = $this->GetReferenceTimestamp();
        $mode = $this->ReadAttributeString('PeriodMode');

        switch ($mode) {
            case 'week':
                $new = strtotime(($direction < 0 ? '-1 week' : '+1 week'), $reference);
                break;
            case 'month':
                $new = strtotime(($direction < 0 ? '-1 month' : '+1 month'), $reference);
                break;
            case 'year':
                $new = strtotime(($direction < 0 ? '-1 year' : '+1 year'), $reference);
                break;
            case 'day':
            default:
                $new = strtotime(($direction < 0 ? '-1 day' : '+1 day'), $reference);
                break;
        }

        $this->WriteAttributeInteger('ReferenceTimestamp', $new);
        $this->SyncControlsFromAttributes();
        $this->UpdateVisualization();
    }

    private function SyncControlsFromAttributes(): void
    {
        $modeMap = ['day' => 0, 'week' => 1, 'month' => 2, 'year' => 3];
        $mode = $this->ReadAttributeString('PeriodMode');
        $ref = $this->GetReferenceTimestamp();

        if (@IPS_VariableExists($this->GetIDForIdent(self::IDENT_PERIOD_MODE))) {
            @SetValue($this->GetIDForIdent(self::IDENT_PERIOD_MODE), $modeMap[$mode] ?? 0);
        }
        if (@IPS_VariableExists($this->GetIDForIdent(self::IDENT_REFERENCE_DATE))) {
            @SetValue($this->GetIDForIdent(self::IDENT_REFERENCE_DATE), strtotime(date('Y-m-d 00:00:00', $ref)));
        }
        foreach ([self::IDENT_ACTION_PREV, self::IDENT_ACTION_TODAY, self::IDENT_ACTION_NEXT] as $ident) {
            if (@IPS_VariableExists($this->GetIDForIdent($ident))) {
                @SetValue($this->GetIDForIdent($ident), 0);
            }
        }
    }

    private function RegisterProfiles(): void
    {
        if (!IPS_VariableProfileExists('EDB.PeriodMode')) {
            IPS_CreateVariableProfile('EDB.PeriodMode', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('EDB.PeriodMode', 0, 'Tag', '', -1);
            IPS_SetVariableProfileAssociation('EDB.PeriodMode', 1, 'Woche', '', -1);
            IPS_SetVariableProfileAssociation('EDB.PeriodMode', 2, 'Monat', '', -1);
            IPS_SetVariableProfileAssociation('EDB.PeriodMode', 3, 'Jahr', '', -1);
        }
        if (!IPS_VariableProfileExists('EDB.TriggerPrev')) {
            IPS_CreateVariableProfile('EDB.TriggerPrev', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('EDB.TriggerPrev', 0, '-', '', -1);
            IPS_SetVariableProfileAssociation('EDB.TriggerPrev', 1, '◀', '', -1);
        }
        if (!IPS_VariableProfileExists('EDB.TriggerToday')) {
            IPS_CreateVariableProfile('EDB.TriggerToday', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('EDB.TriggerToday', 0, '-', '', -1);
            IPS_SetVariableProfileAssociation('EDB.TriggerToday', 1, 'Heute', '', -1);
        }
        if (!IPS_VariableProfileExists('EDB.TriggerNext')) {
            IPS_CreateVariableProfile('EDB.TriggerNext', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('EDB.TriggerNext', 0, '-', '', -1);
            IPS_SetVariableProfileAssociation('EDB.TriggerNext', 1, '▶', '', -1);
        }
    }

    public function UpdateVisualization(): void
    {
        $pvID   = $this->ReadPropertyInteger('PvPowerID');
        $gridID = $this->ReadPropertyInteger('GridPowerID');
        $loadID = $this->ReadPropertyInteger('LoadPowerID');

        if (!$this->IsValidVar($pvID) || !$this->IsValidVar($gridID) || !$this->IsValidVar($loadID)) {
            throw new Exception('Bitte mindestens PV-, Netz- und Verbrauchs-Variable konfigurieren.');
        }

        $archiveID = $this->GetArchiveId();
        if ($archiveID === 0) {
            throw new Exception('Keine Archivsteuerung gefunden.');
        }

        [$rangeStart, $rangeEnd, $label, $isCurrentPeriod] = $this->GetSelectedRange();
        $sourceChart = $this->BuildSourceChartData($archiveID, $rangeStart, $rangeEnd);
        $usageChart  = $this->BuildUsageChartData($archiveID, $rangeStart, $rangeEnd);
        $totals = $this->ResolveTotalsForRange($archiveID, $rangeStart, $rangeEnd, $sourceChart);

        SetValue($this->GetIDForIdent(self::IDENT_OVERVIEW), $this->GetOverviewHtml($totals));
        SetValue($this->GetIDForIdent(self::IDENT_SOURCES), $this->GetSourcesHtml($sourceChart, $label));
        SetValue($this->GetIDForIdent(self::IDENT_USAGE), $this->GetUsageHtml($usageChart, $totals, $label));
        SetValue($this->GetIDForIdent(self::IDENT_NAV), $this->GetNavigationHtml($label, $isCurrentPeriod));
        $this->SyncControlsFromAttributes();
    }

    private function GetSelectedRange(): array
    {
        $reference = $this->GetReferenceTimestamp();
        $now = time();
        $mode = $this->ReadAttributeString('PeriodMode');

        switch ($mode) {
            case 'week':
                $start = strtotime('monday this week', $reference);
                if ((int) date('N', $reference) === 7) {
                    $start = strtotime('monday last week', $reference);
                }
                $endBoundary = strtotime('+1 week', $start);
                $label = date('d.m.Y', $start) . ' - ' . date('d.m.Y', strtotime('-1 day', $endBoundary));
                break;
            case 'month':
                $start = strtotime(date('Y-m-01 00:00:00', $reference));
                $endBoundary = strtotime('+1 month', $start);
                $label = date('F Y', $start);
                break;
            case 'year':
                $start = strtotime(date('Y-01-01 00:00:00', $reference));
                $endBoundary = strtotime('+1 year', $start);
                $label = date('Y', $start);
                break;
            case 'day':
            default:
                $start = strtotime(date('Y-m-d 00:00:00', $reference));
                $endBoundary = strtotime('+1 day', $start);
                $label = date('d. F Y', $start);
                break;
        }

        $isCurrentPeriod = ($now >= $start && $now < $endBoundary);
        $end = $isCurrentPeriod ? $now : $endBoundary;
        if ($end <= $start) {
            $end = $start + 3600;
        }
        return [$start, $end, $label, $isCurrentPeriod];
    }

    private function GetReferenceTimestamp(): int
    {
        $ts = $this->ReadAttributeInteger('ReferenceTimestamp');
        return ($ts > 0) ? $ts : time();
    }

    private function ResolveTotalsForRange(int $archiveID, int $rangeStart, int $rangeEnd, array $sourceChart): array
    {
        $totals = $this->CalculateTotalsFromSourceData($sourceChart);
        $mode = $this->ReadAttributeString('PeriodMode');

        if ($mode === 'day') {
            if ($this->ReadPropertyBoolean('UseHistoricalDayEnergy')) {
                $dayValues = $this->ReadHistoricalDayEnergy($archiveID, $rangeStart);
                if ($dayValues !== null) {
                    return $this->FinalizeTotals($dayValues);
                }
            }
            if ($this->ReadPropertyBoolean('UseHistoricalCounterDiff')) {
                $counterValues = $this->ReadHistoricalCounterDiff($archiveID, $rangeStart, strtotime('+1 day', $rangeStart));
                if ($counterValues !== null) {
                    return $this->FinalizeTotals($counterValues);
                }
            }
            return $this->FinalizeTotals($totals);
        }

        if ($this->ReadPropertyBoolean('UseHistoricalCounterDiff')) {
            $counterValues = $this->ReadHistoricalCounterDiff($archiveID, $rangeStart, $rangeEnd);
            if ($counterValues !== null) {
                return $this->FinalizeTotals($counterValues);
            }
        }

        if ($this->ReadPropertyBoolean('UseHistoricalDayEnergy')) {
            $summed = $this->ReadHistoricalDayEnergyRange($archiveID, $rangeStart, $rangeEnd);
            if ($summed !== null) {
                return $this->FinalizeTotals($summed);
            }
        }

        return $this->FinalizeTotals($totals);
    }

    

    private function ReadHistoricalDayEnergy(int $archiveID, int $dayStart): ?array
    {
        $map = [
            'pv'               => 'PvEnergyDayID',
            'gridImport'       => 'GridImportEnergyDayID',
            'gridExport'       => 'GridExportEnergyDayID',
            'load'             => 'LoadEnergyDayID',
            'batteryCharge'    => 'BatteryChargeEnergyDayID',
            'batteryDischarge' => 'BatteryDischargeEnergyDayID'
        ];
        $values = [];
        $foundAny = false;
        foreach ($map as $key => $property) {
            $id = $this->ReadPropertyInteger($property);
            if ($this->IsValidVar($id)) {
                $val = $this->ReadDayValueFromDailyHistory($archiveID, $id, $dayStart);
                if ($val !== null) {
                    $values[$key] = round($val, 2);
                    $foundAny = true;
                }
            }
        }
        return $foundAny ? $values : null;
    }


    private function ReadHistoricalDayEnergyRange(int $archiveID, int $rangeStart, int $rangeEnd): ?array
    {
        $sum = [
            'pv' => 0.0,
            'gridImport' => 0.0,
            'gridExport' => 0.0,
            'load' => 0.0,
            'batteryCharge' => 0.0,
            'batteryDischarge' => 0.0
        ];
        $foundAny = false;

        for ($day = strtotime(date('Y-m-d 00:00:00', $rangeStart)); $day < $rangeEnd; $day = strtotime('+1 day', $day)) {
            $vals = $this->ReadHistoricalDayEnergy($archiveID, $day);
            if ($vals !== null) {
                foreach ($sum as $k => $v) {
                    $sum[$k] += (float) ($vals[$k] ?? 0.0);
                }
                $foundAny = true;
            }
        }

        if (!$foundAny) {
            return null;
        }

        foreach ($sum as $k => $v) {
            $sum[$k] = round($v, 2);
        }
        return $sum;
    }

    private function ReadHistoricalCounterDiff(int $archiveID, int $dayStart, int $dayEnd): ?array
    {
        $map = [
            'pv'               => 'PvEnergyTotalID',
            'gridImport'       => 'GridImportEnergyTotalID',
            'gridExport'       => 'GridExportEnergyTotalID',
            'load'             => 'LoadEnergyTotalID',
            'batteryCharge'    => 'BatteryChargeEnergyTotalID',
            'batteryDischarge' => 'BatteryDischargeEnergyTotalID'
        ];
        $values = [];
        $foundAny = false;
        foreach ($map as $key => $property) {
            $id = $this->ReadPropertyInteger($property);
            if ($this->IsValidVar($id)) {
                $diff = $this->ReadCounterDiffForDay($archiveID, $id, $dayStart, $dayEnd);
                if ($diff !== null) {
                    $values[$key] = round(max(0.0, $diff), 2);
                    $foundAny = true;
                }
            }
        }
        return $foundAny ? $values : null;
    }

    private function ReadDayValueFromDailyHistory(int $archiveID, int $varID, int $dayStart): ?float
    {
        $rows = @AC_GetAggregatedValues($archiveID, $varID, 1, $dayStart, strtotime('+1 day', $dayStart), 0);
        if (!is_array($rows) || count($rows) === 0) {
            return null;
        }
        foreach ($rows as $row) {
            $ts = (int) $row['TimeStamp'];
            if (date('Y-m-d', $ts) === date('Y-m-d', $dayStart)) {
                foreach (['Avg', 'Value', 'Max'] as $field) {
                    if (isset($row[$field])) {
                        return (float) $row[$field];
                    }
                }
            }
        }
        return null;
    }

    private function ReadCounterDiffForDay(int $archiveID, int $varID, int $dayStart, int $dayEnd): ?float
    {
        $rows = @AC_GetLoggedValues($archiveID, $varID, $dayStart - 3600, $dayEnd + 3600, 0);
        if (!is_array($rows) || count($rows) === 0) {
            return null;
        }
        $startValue = null;
        $endValue = null;
        foreach ($rows as $row) {
            $ts = (int) $row['TimeStamp'];
            $val = (float) $row['Value'];
            if ($ts <= $dayStart) {
                $startValue = $val;
            }
            if ($ts <= $dayEnd) {
                $endValue = $val;
            }
        }
        if ($startValue === null) $startValue = (float) $rows[0]['Value'];
        if ($endValue === null) $endValue = (float) $rows[count($rows) - 1]['Value'];
        return $endValue - $startValue;
    }

    private function FinalizeTotals(array $totals): array
    {
        $totals = array_merge([
            'pv' => 0.0,
            'gridImport' => 0.0,
            'gridExport' => 0.0,
            'load' => 0.0,
            'batteryCharge' => 0.0,
            'batteryDischarge' => 0.0
        ], $totals);
        $totals['selfConsumption'] = round(max(0.0, $totals['pv'] - $totals['gridExport']), 2);
        $totals['netUsage'] = round($totals['load'] - $totals['gridExport'], 2);
        $totals['autarky'] = $totals['load'] > 0 ? round(min(100.0, max(0.0, (($totals['load'] - $totals['gridImport']) / $totals['load']) * 100.0)), 1) : 0.0;
        return $totals;
    }

    private function IsValidVar(int $id): bool { return $id > 0 && @IPS_VariableExists($id); }
    private function ApplySign(float $value, bool $invert): float { return $invert ? -$value : $value; }

    private function GetArchiveId(): int
    {
        $configured = $this->ReadPropertyInteger('ArchiveControlID');
        if ($configured > 0 && @IPS_InstanceExists($configured)) return $configured;
        $list = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        return count($list) > 0 ? $list[0] : 0;
    }

    private function GetAggregatedSeriesKw(int $archiveID, int $varID, int $aggregation, int $start, int $end, bool $invert): array
    {
        if (!$this->IsValidVar($varID)) return [];
        $rows = @AC_GetAggregatedValues($archiveID, $varID, $aggregation, $start, $end, 0);
        if (!is_array($rows)) return [];
        $result = [];
        foreach ($rows as $row) {
            $ts = (int) $row['TimeStamp'];
            $value = isset($row['Avg']) ? (float) $row['Avg'] : ((isset($row['Value'])) ? (float) $row['Value'] : null);
            if ($value === null) continue;
            $result[$ts] = round($this->ApplySign($value, $invert) / 1000.0, 3);
        }
        ksort($result);
        return $result;
    }

    private function BuildSourceChartData(int $archiveID, int $start, int $end): array
    {
        $aggregation = $this->ReadPropertyInteger('SourceAggregation');
        $aligned = $this->AlignSeriesByTimestamp([
            'pv' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('PvPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertPv')),
            'grid' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('GridPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertGrid')),
            'load' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('LoadPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertLoad')),
            'battery' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('BatteryPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertBattery'))
        ]);
        return $this->ReduceAlignedSeries($aligned, max(24, $this->ReadPropertyInteger('MaxSourcePoints')));
    }

    private function BuildUsageChartData(int $archiveID, int $start, int $end): array
    {
        $aggregation = $this->ReadPropertyInteger('UsageAggregation');
        $aligned = $this->AlignSeriesByTimestamp([
            'pv' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('PvPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertPv')),
            'grid' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('GridPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertGrid')),
            'load' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('LoadPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertLoad')),
            'battery' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('BatteryPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertBattery'))
        ]);

        $buckets = [];
        $count = count($aligned['timestamps']);
        if ($count < 2) return [];
        for ($i = 1; $i < $count; $i++) {
            $from = (int) $aligned['timestamps'][$i - 1];
            $to = (int) $aligned['timestamps'][$i];
            $dtHours = max(1 / 60, ($to - $from) / 3600.0);
            $pv = max(0.0, (float) $aligned['pv'][$i - 1]);
            $grid = (float) $aligned['grid'][$i - 1];
            $load = max(0.0, (float) $aligned['load'][$i - 1]);
            $battery = (float) $aligned['battery'][$i - 1];
            $buckets[] = [
                'label' => date('H:i', $from),
                'pvToLoad' => round(min($pv, $load) * $dtHours, 3),
                'gridImport' => round(max(0.0, $grid) * $dtHours, 3),
                'batteryCharge' => round(max(0.0, -$battery) * $dtHours, 3),
                'batteryDischarge' => round(max(0.0, $battery) * $dtHours, 3),
                'gridExport' => round(max(0.0, -$grid) * $dtHours, 3)
            ];
        }
        return $this->ReduceUsageBuckets($buckets, max(8, $this->ReadPropertyInteger('MaxUsagePoints')));
    }

    private function AlignSeriesByTimestamp(array $series): array
    {
        $allTimestamps = [];
        foreach ($series as $rows) foreach ($rows as $ts => $value) $allTimestamps[$ts] = true;
        ksort($allTimestamps);
        $timestamps = array_keys($allTimestamps);
        $aligned = ['timestamps' => [], 'labels' => [], 'pv' => [], 'grid' => [], 'load' => [], 'battery' => []];
        $last = ['pv' => 0.0, 'grid' => 0.0, 'load' => 0.0, 'battery' => 0.0];
        foreach ($timestamps as $ts) {
            $aligned['timestamps'][] = $ts;
            $aligned['labels'][] = date('H:i', $ts);
            foreach (['pv', 'grid', 'load', 'battery'] as $name) {
                if (isset($series[$name][$ts])) $last[$name] = $series[$name][$ts];
                $aligned[$name][] = round($last[$name], 3);
            }
        }
        return $aligned;
    }

    private function ReduceAlignedSeries(array $aligned, int $maxPoints): array
    {
        $count = count($aligned['labels']);
        if ($count <= $maxPoints) return $aligned;
        $step = (int) ceil($count / $maxPoints);
        $reduced = ['timestamps' => [], 'labels' => [], 'pv' => [], 'grid' => [], 'load' => [], 'battery' => []];
        for ($i = 0; $i < $count; $i += $step) {
            $sliceEnd = min($i + $step, $count);
            $reduced['timestamps'][] = $aligned['timestamps'][$sliceEnd - 1];
            $reduced['labels'][] = $aligned['labels'][$sliceEnd - 1];
            $reduced['pv'][] = round($this->AverageSlice($aligned['pv'], $i, $sliceEnd), 3);
            $reduced['grid'][] = round($this->AverageSlice($aligned['grid'], $i, $sliceEnd), 3);
            $reduced['load'][] = round($this->AverageSlice($aligned['load'], $i, $sliceEnd), 3);
            $reduced['battery'][] = round($this->AverageSlice($aligned['battery'], $i, $sliceEnd), 3);
        }
        return $reduced;
    }

    private function ReduceUsageBuckets(array $rows, int $maxPoints): array
    {
        $count = count($rows);
        if ($count <= $maxPoints) return $rows;
        $step = (int) ceil($count / $maxPoints);
        $reduced = [];
        for ($i = 0; $i < $count; $i += $step) {
            $slice = array_slice($rows, $i, $step);
            $last = $slice[count($slice) - 1];
            $reduced[] = [
                'label' => $last['label'],
                'pvToLoad' => round($this->SumColumn($slice, 'pvToLoad'), 3),
                'gridImport' => round($this->SumColumn($slice, 'gridImport'), 3),
                'batteryCharge' => round($this->SumColumn($slice, 'batteryCharge'), 3),
                'batteryDischarge' => round($this->SumColumn($slice, 'batteryDischarge'), 3),
                'gridExport' => round($this->SumColumn($slice, 'gridExport'), 3)
            ];
        }
        return $reduced;
    }

    private function CalculateTotalsFromSourceData(array $sourceChart): array
    {
        $pvEnergy = 0.0; $gridImport = 0.0; $gridExport = 0.0; $loadEnergy = 0.0; $batteryCharge = 0.0; $batteryDischarge = 0.0;
        $count = count($sourceChart['timestamps']);
        if ($count < 2) return ['pv' => 0.0, 'gridImport' => 0.0, 'gridExport' => 0.0, 'load' => 0.0, 'batteryCharge' => 0.0, 'batteryDischarge' => 0.0];
        for ($i = 1; $i < $count; $i++) {
            $from = (int) $sourceChart['timestamps'][$i - 1];
            $to = (int) $sourceChart['timestamps'][$i];
            $dtHours = max(1 / 60, ($to - $from) / 3600.0);
            $pv = max(0.0, (float) $sourceChart['pv'][$i - 1]);
            $grid = (float) $sourceChart['grid'][$i - 1];
            $load = max(0.0, (float) $sourceChart['load'][$i - 1]);
            $battery = (float) $sourceChart['battery'][$i - 1];
            $pvEnergy += $pv * $dtHours; $loadEnergy += $load * $dtHours;
            if ($grid >= 0) $gridImport += $grid * $dtHours; else $gridExport += abs($grid) * $dtHours;
            if ($battery >= 0) $batteryDischarge += $battery * $dtHours; else $batteryCharge += abs($battery) * $dtHours;
        }
        return ['pv' => round($pvEnergy, 2), 'gridImport' => round($gridImport, 2), 'gridExport' => round($gridExport, 2), 'load' => round($loadEnergy, 2), 'batteryCharge' => round($batteryCharge, 2), 'batteryDischarge' => round($batteryDischarge, 2)];
    }

    private function SumColumn(array $rows, string $column): float { $sum = 0.0; foreach ($rows as $row) $sum += (float) $row[$column]; return $sum; }
    private function AverageSlice(array $values, int $start, int $end): float { $slice = array_slice($values, $start, $end - $start); return count($slice) === 0 ? 0.0 : array_sum($slice) / count($slice); }

    private function GetOverviewHtml(array $t): string
    {
        return '<div style="font-family:Arial,sans-serif;padding:12px;color:#222;"><style>.edb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}.edb-card{background:#f7f7f7;border:1px solid #d9d9d9;border-radius:18px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.edb-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}.edb-title{font-size:24px;font-weight:700}.edb-badge{font-size:18px;font-weight:700;padding:10px 14px;background:#fff;border:1px solid #d0d0d0;border-radius:14px}.edb-box{background:#fff;border:1px solid #e1e1e1;border-radius:12px;padding:12px}.edb-label{font-size:12px;color:#666;margin-bottom:4px}.edb-value{font-size:20px;font-weight:700}</style><div class="edb-card"><div class="edb-head"><div class="edb-title">Verbrauchsübersicht</div><div class="edb-badge">+' . $this->Fmt($t['netUsage']) . ' kWh</div></div><div class="edb-grid">' .
            $this->OverviewBox('PV', $this->Fmt($t['pv']) . ' kWh') .
            $this->OverviewBox('Bezug', $this->Fmt($t['gridImport']) . ' kWh') .
            $this->OverviewBox('Einspeisung', $this->Fmt($t['gridExport']) . ' kWh') .
            $this->OverviewBox('Verbrauch', $this->Fmt($t['load']) . ' kWh') .
            $this->OverviewBox('Batt. Laden', $this->Fmt($t['batteryCharge']) . ' kWh') .
            $this->OverviewBox('Batt. Entladen', $this->Fmt($t['batteryDischarge']) . ' kWh') .
            $this->OverviewBox('Eigenverbrauch', $this->Fmt($t['selfConsumption']) . ' kWh') .
            $this->OverviewBox('Autarkie', $this->Fmt($t['autarky']) . ' %') .
            '</div></div></div>';
    }

    private function OverviewBox(string $label, string $value): string
    {
        return '<div class="edb-box"><div class="edb-label">' . htmlspecialchars($label) . '</div><div class="edb-value">' . htmlspecialchars($value) . '</div></div>';
    }

    private function GetSourcesHtml(array $data, string $label): string
    {
        $json = json_encode(['labels' => $data['labels'], 'pv' => $data['pv'], 'grid' => $data['grid'], 'load' => $data['load'], 'battery' => $data['battery']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $height = max(220, min(420, 220 + (int) floor(count($data['labels']) / 4)));
        $labelEsc = htmlspecialchars($label);
        return '<div style="font-family:Arial,sans-serif;padding:12px;color:#222;"><style>.edb-card{background:#f7f7f7;border:1px solid #d9d9d9;border-radius:18px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.edb-title{font-size:24px;font-weight:700;margin-bottom:2px}.edb-sub{font-size:13px;color:#666;margin-bottom:8px}.edb-wrap{position:relative;height:' . $height . 'px}</style><div class="edb-card"><div class="edb-title">Stromquellen</div><div class="edb-sub">' . $labelEsc . '</div><div class="edb-wrap"><canvas id="edbSourceChart"></canvas></div></div><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>(function(){const d=' . $json . ';new Chart(document.getElementById("edbSourceChart"),{type:"line",data:{labels:d.labels,datasets:[{label:"PV",data:d.pv,borderColor:"rgba(255,152,0,1)",backgroundColor:"rgba(255,152,0,.18)",fill:true,tension:.25,pointRadius:0},{label:"Netz",data:d.grid,borderColor:"rgba(0,188,212,1)",backgroundColor:"rgba(0,188,212,.06)",tension:.2,pointRadius:0},{label:"Verbrauch",data:d.load,borderColor:"rgba(0,0,0,.85)",backgroundColor:"rgba(0,0,0,0)",borderDash:[6,4],tension:.2,pointRadius:0},{label:"Batterie",data:d.battery,borderColor:"rgba(63,81,181,1)",backgroundColor:"rgba(63,81,181,.06)",tension:.2,pointRadius:0,hidden:d.battery.every(v=>v===0)}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:"index",intersect:false},plugins:{legend:{position:"top"}},scales:{y:{title:{display:true,text:"kW"}},x:{ticks:{maxTicksLimit:8}}}}});})();</script></div>';
    }

    private function GetUsageHtml(array $data, array $totals, string $label): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $height = max(220, min(460, 220 + (int) floor(count($data) * 5)));
        $labelEsc = htmlspecialchars($label);
        $badge = '+' . $this->Fmt($totals['netUsage']) . ' kWh';
        return '<div style="font-family:Arial,sans-serif;padding:12px;color:#222;"><style>.edb-card{background:#f7f7f7;border:1px solid #d9d9d9;border-radius:18px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.edb-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:4px}.edb-title{font-size:24px;font-weight:700}.edb-sub{font-size:13px;color:#666;margin-bottom:8px}.edb-badge{font-size:18px;font-weight:700;padding:10px 14px;background:#fff;border:1px solid #d0d0d0;border-radius:14px}.edb-wrap{position:relative;height:' . $height . 'px}</style><div class="edb-card"><div class="edb-head"><div class="edb-title">Stromnutzung</div><div class="edb-badge">' . $badge . '</div></div><div class="edb-sub">' . $labelEsc . '</div><div class="edb-wrap"><canvas id="edbUsageChart"></canvas></div></div><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>(function(){const d=' . $json . ';new Chart(document.getElementById("edbUsageChart"),{type:"bar",data:{labels:d.map(x=>x.label),datasets:[{label:"PV → Last",data:d.map(x=>x.pvToLoad),backgroundColor:"rgba(255,193,7,.55)",borderColor:"rgba(255,152,0,1)",borderWidth:1,stack:"energy"},{label:"Netzbezug",data:d.map(x=>x.gridImport),backgroundColor:"rgba(128,203,196,.75)",borderColor:"rgba(77,182,172,1)",borderWidth:1,stack:"energy"},{label:"Batt. Entladen",data:d.map(x=>x.batteryDischarge),backgroundColor:"rgba(100,181,246,.75)",borderColor:"rgba(66,165,245,1)",borderWidth:1,stack:"energy"},{label:"Batt. Laden",data:d.map(x=>-x.batteryCharge),backgroundColor:"rgba(244,143,177,.72)",borderColor:"rgba(236,64,122,1)",borderWidth:1,stack:"energy"},{label:"Netzeinspeisung",data:d.map(x=>-x.gridExport),backgroundColor:"rgba(179,157,219,.72)",borderColor:"rgba(126,87,194,1)",borderWidth:1,stack:"energy"}]},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:"index",intersect:false},plugins:{legend:{position:"top"}},scales:{y:{title:{display:true,text:"kWh"},stacked:true},x:{stacked:true}}}});})();</script></div>';
    }

    private function GetNavigationHtml(string $label, bool $isCurrentPeriod): string
    {
        $modeMap = ['day' => 'Tag', 'week' => 'Woche', 'month' => 'Monat', 'year' => 'Jahr'];
        $mode = $modeMap[$this->ReadAttributeString('PeriodMode')] ?? 'Tag';
        $state = $isCurrentPeriod ? 'Aktueller Zeitraum' : 'Historischer Zeitraum';
        return '<div style="font-family:Arial,sans-serif;padding:12px;color:#222;"><style>.edb-nav{display:flex;align-items:center;justify-content:space-between;gap:10px;background:#5b5b5b;color:#fff;border-radius:16px;padding:12px 18px;box-shadow:0 2px 8px rgba(0,0,0,.18)}.edb-left{display:flex;align-items:center;gap:12px;font-weight:700;font-size:18px}.edb-cal{font-size:18px;line-height:1}.edb-right{display:flex;align-items:center;gap:10px}.edb-chip{display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border-radius:999px;background:#dff3ff;color:#039be5;font-weight:700}.edb-sub{font-size:12px;opacity:.8}</style><div class="edb-nav"><div class="edb-left"><div class="edb-cal">&#128197;</div><div><div>' . htmlspecialchars($label) . '</div><div class="edb-sub">' . $mode . ' · ' . $state . '</div></div></div><div class="edb-right"><div class="edb-chip">' . $mode . '</div></div></div></div>';
    }

    private function Fmt(float $value): string { return number_format($value, 2, ',', '.'); }
    private function RenderErrorHtml(string $message): string { return '<div style="padding:16px;font-family:Arial,sans-serif;color:#a94442;background:#f2dede;border:1px solid #ebccd1;border-radius:8px;">' . htmlspecialchars($message) . '</div>'; }
}
