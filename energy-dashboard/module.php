<?php

declare(strict_types=1);

class EnergyDashboard extends IPSModule
{
    private const IDENT_OVERVIEW = 'OverviewHTML';
    private const IDENT_SOURCES  = 'SourcesHTML';
    private const IDENT_USAGE    = 'UsageHTML';
    private const IDENT_SANKEY   = 'SankeyHTML';
    private const IDENT_SANKEY_LIVE = 'SankeyLiveHTML';

    private const IDENT_PERIOD_MODE    = 'WF_PeriodMode';
    private const IDENT_REFERENCE_DATE = 'WF_ReferenceDate';
    private const IDENT_ACTION_PREV    = 'WF_ActionPrev';
    private const IDENT_ACTION_TODAY   = 'WF_ActionToday';
    private const IDENT_ACTION_NEXT    = 'WF_ActionNext';

    private const TIMER_REFRESH = 'Refresh';

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

        $this->RegisterPropertyString('BatteryContentMode', 'none');
        $this->RegisterPropertyInteger('BatterySocID', 0);
        $this->RegisterPropertyInteger('BatteryContentKwhID', 0);
        $this->RegisterPropertyString('BatteryUsableCapacityKwh', '0');
        $this->RegisterPropertyInteger('BatteryCyclesID', 0);
        $this->RegisterPropertyBoolean('ShowSocOverlay', true);

        $this->RegisterPropertyBoolean('EnableTargetComparison', false);
        $this->RegisterPropertyInteger('PvTargetDayID', 0);
        $this->RegisterPropertyInteger('PvTargetTotalID', 0);
        $this->RegisterPropertyBoolean('ShowPeakValues', false);
        $this->RegisterPropertyBoolean('ShowPeakTimestamps', false);
        $this->RegisterPropertyBoolean('ShowPeakTimestampsLong', false);
        $this->RegisterPropertyBoolean('ShowPeakMarkersPvLoad', false);
        $this->RegisterPropertyBoolean('EnablePeakHoverDebug', false);
        $this->RegisterPropertyBoolean('ShowBalanceBadge', true);
        $this->RegisterPropertyBoolean('ColorBalanceBadgeByState', true);

        $this->RegisterPropertyString('ThemePreset', 'custom');
        $this->RegisterPropertyString('ThemeMode', 'light');
                                                                                        
        $this->RegisterPropertyBoolean('ShowSankeyPeriod', true);
        $this->RegisterPropertyBoolean('ShowSankeyLive', true);
        $this->RegisterPropertyBoolean('SankeyUseLiveWatts', true);
        $this->RegisterPropertyInteger('SankeyLivePvID', 0);
        $this->RegisterPropertyInteger('SankeyLiveGridID', 0);
        $this->RegisterPropertyInteger('SankeyLiveLoadID', 0);
        $this->RegisterPropertyInteger('SankeyLiveBatteryID', 0);
        $this->RegisterPropertyInteger('SankeyLiveRefresh', 30);
        $this->RegisterPropertyBoolean('SankeyShowPercentages', true);
        $this->RegisterPropertyBoolean('SankeyAnimate', true);

        $this->RegisterPropertyString('ViewModeWeekSources', 'hours');
        $this->RegisterPropertyString('ViewModeWeekUsage', 'hours');
        $this->RegisterPropertyString('ViewModeMonthSources', 'days');
        $this->RegisterPropertyString('ViewModeMonthUsage', 'days');
        $this->RegisterPropertyString('ViewModeYearSources', 'weeks');
        $this->RegisterPropertyString('ViewModeYearUsage', 'weeks');

        $this->RegisterPropertyInteger('ArchiveControlID', 0);
        $this->RegisterPropertyInteger('SourceAggregation', 5);
        $this->RegisterPropertyInteger('UsageAggregation', 0);
        $this->RegisterPropertyInteger('RefreshSeconds', 300);
        $this->RegisterPropertyInteger('MaxSourcePoints', 180);
        $this->RegisterPropertyInteger('MaxUsagePoints', 24);

        $this->RegisterAttributeString('PeriodMode', 'day');
        $this->RegisterAttributeInteger('ReferenceTimestamp', 0);
        $this->RegisterAttributeString('LastAppliedThemePreset', '');

        $this->RegisterTimer(self::TIMER_REFRESH, 0, 'EDB_UpdateVisualization($_IPS["TARGET"]);');
    }


    public function ResetThemeDefaults(): void
    {
        IPS_SetProperty($this->InstanceID, 'ThemePreset', 'light');
        IPS_ApplyChanges($this->InstanceID);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainVariable(self::IDENT_OVERVIEW, 'Verbrauchsübersicht', VARIABLETYPE_STRING, '~HTMLBox', 0, true);
        $this->MaintainVariable(self::IDENT_SOURCES, 'Stromquellen', VARIABLETYPE_STRING, '~HTMLBox', 1, true);
        $this->MaintainVariable(self::IDENT_USAGE, 'Stromnutzung', VARIABLETYPE_STRING, '~HTMLBox', 2, true);
        $this->MaintainVariable(self::IDENT_SANKEY, 'Energiefluss Sankey kWh', VARIABLETYPE_STRING, '~HTMLBox', 3, true);
        $this->MaintainVariable(self::IDENT_SANKEY_LIVE, 'Energiefluss Sankey Live', VARIABLETYPE_STRING, '~HTMLBox', 4, true);

        if ($this->ReadPropertyBoolean('CreateWebFrontControls')) {
            $this->EnsureWebFrontControls();
        } else {
            $this->MaintainVariable(self::IDENT_PERIOD_MODE, 'Intervall', VARIABLETYPE_INTEGER, '', 10, false);
            $this->MaintainVariable(self::IDENT_REFERENCE_DATE, 'Referenzdatum', VARIABLETYPE_INTEGER, '', 11, false);
            $this->MaintainVariable(self::IDENT_ACTION_PREV, 'Zurück', VARIABLETYPE_INTEGER, '', 12, false);
            $this->MaintainVariable(self::IDENT_ACTION_TODAY, 'Heute', VARIABLETYPE_INTEGER, '', 13, false);
            $this->MaintainVariable(self::IDENT_ACTION_NEXT, 'Vor', VARIABLETYPE_INTEGER, '', 14, false);
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
            @$this->SetValue(self::IDENT_OVERVIEW, $error);
            @$this->SetValue(self::IDENT_SOURCES, $error);
            @$this->SetValue(self::IDENT_USAGE, $error);
            @$this->SetValue(self::IDENT_SANKEY, $error);
            @$this->SetValue(self::IDENT_SANKEY_LIVE, $error);
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
                $this->WriteAttributeString('PeriodMode', $modeMap[(int) $Value] ?? 'day');
                $this->UpdateVisualization();
                break;

            case self::IDENT_REFERENCE_DATE:
                $newDay = strtotime(date('Y-m-d 12:00:00', (int) $Value));
                if ($newDay === false) {
                    $newDay = time();
                }
                $old = $this->GetReferenceTimestamp();
                $hour = (int) date('H', $old);
                $minute = (int) date('i', $old);
                $second = (int) date('s', $old);
                $final = mktime($hour, $minute, $second, (int) date('n', $newDay), (int) date('j', $newDay), (int) date('Y', $newDay));
                $this->WriteAttributeInteger('ReferenceTimestamp', $final);
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
        if (!$this->IsValidVar($this->ReadPropertyInteger('PvPowerID')) ||
            !$this->IsValidVar($this->ReadPropertyInteger('GridPowerID')) ||
            !$this->IsValidVar($this->ReadPropertyInteger('LoadPowerID'))) {
            throw new Exception('Bitte mindestens PV-, Netz- und Verbrauchs-Variable konfigurieren.');
        }

        $archiveID = $this->GetArchiveId();
        if ($archiveID === 0) {
            throw new Exception('Keine Archivsteuerung gefunden.');
        }

        [$rangeStart, $rangeEnd, $label, $isCurrentPeriod] = $this->GetSelectedRange();

        $sourceChart = $this->BuildSourceChartData($archiveID, $rangeStart, $rangeEnd);
        $usageChart  = $this->BuildUsageChartData($archiveID, $rangeStart, $rangeEnd);
        $totals      = $this->ResolveTotalsForRange($archiveID, $rangeStart, $rangeEnd, $sourceChart);
        $targetComparison = $this->GetTargetComparisonTotals($archiveID, $rangeStart, $rangeEnd, $totals);
        $peakValues = $this->GetPeakValues($archiveID, $rangeStart, $rangeEnd);

        $this->SetValue(self::IDENT_OVERVIEW, $this->GetOverviewHtml($totals, $targetComparison, $peakValues));
        $this->SetValue(self::IDENT_SOURCES, $this->GetSourcesHtml($sourceChart, $label, $peakValues));
        $this->SetValue(self::IDENT_USAGE, $this->GetUsageHtml($usageChart, $totals, $label));
        $this->SetValue(self::IDENT_SANKEY, $this->GetSankeyHtml($totals, $label, false));
        $this->SetValue(self::IDENT_SANKEY_LIVE, $this->GetSankeyHtml($totals, $label, true));
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

    private function GetConfiguredViewMode(string $mode, string $chart): string
    {
        if ($mode === 'day') {
            return 'hours';
        }

        $prop = '';
        if ($mode === 'week') {
            $prop = ($chart === 'sources') ? 'ViewModeWeekSources' : 'ViewModeWeekUsage';
        } elseif ($mode === 'month') {
            $prop = ($chart === 'sources') ? 'ViewModeMonthSources' : 'ViewModeMonthUsage';
        } elseif ($mode === 'year') {
            $prop = ($chart === 'sources') ? 'ViewModeYearSources' : 'ViewModeYearUsage';
        }

        $value = $this->ReadPropertyString($prop);
        if ($value === '') {
            return ($mode === 'year') ? 'weeks' : (($mode === 'month') ? 'days' : 'hours');
        }
        return $value;
    }

    private function ResolveTotalsForRange(int $archiveID, int $rangeStart, int $rangeEnd, array $sourceChart): array
    {
        $mode = $this->ReadAttributeString('PeriodMode');

        if ($mode === 'day') {
            $totals = $this->CalculateTotalsFromPowerSeries($sourceChart);

            if ($this->ReadPropertyBoolean('UseHistoricalDayEnergy')) {
                $dayValues = $this->ReadHistoricalDayEnergy($archiveID, $rangeStart);
                if ($dayValues !== null) {
                    return $this->FinalizeTotals($dayValues, $this->GetBatteryContentDeltaKwh($archiveID, $rangeStart, strtotime('+1 day', $rangeStart)), $archiveID, $rangeStart, strtotime('+1 day', $rangeStart));
                }
            }

            if ($this->ReadPropertyBoolean('UseHistoricalCounterDiff')) {
                $counterValues = $this->ReadHistoricalCounterDiff($archiveID, $rangeStart, strtotime('+1 day', $rangeStart));
                if ($counterValues !== null) {
                    return $this->FinalizeTotals($counterValues, $this->GetBatteryContentDeltaKwh($archiveID, $rangeStart, strtotime('+1 day', $rangeStart)), $archiveID, $rangeStart, strtotime('+1 day', $rangeStart));
                }
            }

            return $this->FinalizeTotals($totals, $this->GetBatteryContentDeltaKwh($archiveID, $rangeStart, $rangeEnd), $archiveID, $rangeStart, $rangeEnd);
        }

        $sum = [
            'pv' => 0.0,
            'gridImport' => 0.0,
            'gridExport' => 0.0,
            'load' => 0.0,
            'batteryCharge' => 0.0,
            'batteryDischarge' => 0.0
        ];

        for ($day = strtotime(date('Y-m-d 00:00:00', $rangeStart)); $day < $rangeEnd; $day = strtotime('+1 day', $day)) {
            $vals = $this->ResolveSingleDayTotals($archiveID, $day);
            foreach ($sum as $k => $v) {
                $sum[$k] += (float) ($vals[$k] ?? 0.0);
            }
        }

        foreach ($sum as $k => $v) {
            $sum[$k] = round($v, 2);
        }

        return $this->FinalizeTotals($sum, $this->GetBatteryContentDeltaKwh($archiveID, $rangeStart, $rangeEnd), $archiveID, $rangeStart, $rangeEnd);
    }

    private function ResolveSingleDayTotals(int $archiveID, int $dayStart): array
    {
        $dayEnd = strtotime('+1 day', $dayStart);

        if ($this->ReadPropertyBoolean('UseHistoricalDayEnergy')) {
            $dayValues = $this->ReadHistoricalDayEnergy($archiveID, $dayStart);
            if ($dayValues !== null) {
                return array_merge([
                    'pv' => 0.0,
                    'gridImport' => 0.0,
                    'gridExport' => 0.0,
                    'load' => 0.0,
                    'batteryCharge' => 0.0,
                    'batteryDischarge' => 0.0
                ], $dayValues);
            }
        }

        if ($this->ReadPropertyBoolean('UseHistoricalCounterDiff')) {
            $counterValues = $this->ReadHistoricalCounterDiff($archiveID, $dayStart, $dayEnd);
            if ($counterValues !== null) {
                return array_merge([
                    'pv' => 0.0,
                    'gridImport' => 0.0,
                    'gridExport' => 0.0,
                    'load' => 0.0,
                    'batteryCharge' => 0.0,
                    'batteryDischarge' => 0.0
                ], $counterValues);
            }
        }

        $source = $this->BuildPowerSeries($archiveID, $dayStart, $dayEnd, $this->ReadPropertyInteger('SourceAggregation'));
        return $this->CalculateTotalsFromPowerSeries($source);
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

        if ($startValue === null) {
            $startValue = (float) $rows[0]['Value'];
        }
        if ($endValue === null) {
            $endValue = (float) $rows[count($rows) - 1]['Value'];
        }

        return $endValue - $startValue;
    }



    private function GetBatteryContentNowKwh(int $archiveID, int $timestamp): float
    {
        $mode = $this->ReadPropertyString('BatteryContentMode');
        $isToday = date('Y-m-d', $timestamp) === date('Y-m-d');

        if ($mode === 'kwh') {
            $id = $this->ReadPropertyInteger('BatteryContentKwhID');
            if ($this->IsValidVar($id)) {
                if ($isToday) {
                    return round((float) GetValue($id), 2);
                }
                $val = $this->ReadLoggedValueAtOrBefore($archiveID, $id, $timestamp);
                return ($val !== null) ? round($val, 2) : 0.0;
            }
        }

        if ($mode === 'soc') {
            $id = $this->ReadPropertyInteger('BatterySocID');
            $usable = (float) str_replace(',', '.', $this->ReadPropertyString('BatteryUsableCapacityKwh'));
            if ($this->IsValidVar($id) && $usable > 0) {
                if ($isToday) {
                    return round((((float) GetValue($id)) / 100.0) * $usable, 2);
                }
                $soc = $this->ReadLoggedValueAtOrBefore($archiveID, $id, $timestamp);
                if ($soc !== null) {
                    return round(($soc / 100.0) * $usable, 2);
                }
            }
        }

        return 0.0;
    }

    private function GetBatteryCyclesDelta(int $archiveID, int $rangeStart, int $rangeEnd): float
    {
        $id = $this->ReadPropertyInteger('BatteryCyclesID');
        if (!$this->IsValidVar($id)) {
            return 0.0;
        }

        $start = $this->ReadLoggedValueAtOrBefore($archiveID, $id, $rangeStart);
        if ($start === null) {
            $start = $this->ReadLoggedValueAtOrAfter($archiveID, $id, $rangeStart);
        }

        $end = $this->ReadLoggedValueAtOrBefore($archiveID, $id, $rangeEnd);
        if ($end === null) {
            $end = $this->ReadLoggedValueAtOrAfter($archiveID, $id, $rangeEnd - 3600);
        }

        if ($start === null || $end === null) {
            return 0.0;
        }

        return round(max(0.0, $end - $start), 3);
    }

    private function GetBatteryContentDeltaKwh(int $archiveID, int $rangeStart, int $rangeEnd): float
    {
        $startDay = strtotime(date('Y-m-d 00:00:00', $rangeStart));
        $sumCharge = 0.0;
        $sumDischarge = 0.0;

        for ($day = $startDay; $day < $rangeEnd; $day = strtotime('+1 day', $day)) {
            $dayTotals = $this->ResolveSingleDayTotals($archiveID, $day);
            $sumCharge += (float) ($dayTotals['batteryCharge'] ?? 0.0);
            $sumDischarge += (float) ($dayTotals['batteryDischarge'] ?? 0.0);
        }

        return round($sumCharge - $sumDischarge, 3);
    }

    private function ReadLoggedValueAtOrBefore(int $archiveID, int $varID, int $timestamp): ?float
    {
        $rows = @AC_GetLoggedValues($archiveID, $varID, $timestamp - 7 * 86400, $timestamp + 60, 0);
        if (!is_array($rows) || count($rows) === 0) {
            return null;
        }

        $value = null;
        foreach ($rows as $row) {
            $ts = (int) $row['TimeStamp'];
            if ($ts <= $timestamp && isset($row['Value'])) {
                $value = (float) $row['Value'];
            }
        }

        if ($value === null && isset($rows[0]['Value'])) {
            $value = (float) $rows[0]['Value'];
        }

        return $value;
    }

    private function ReadLoggedValueAtOrAfter(int $archiveID, int $varID, int $timestamp): ?float
    {
        $rows = @AC_GetLoggedValues($archiveID, $varID, $timestamp, $timestamp + 7 * 86400, 0);
        if (!is_array($rows) || count($rows) === 0) {
            return null;
        }

        foreach ($rows as $row) {
            if (isset($row['Value'])) {
                return (float) $row['Value'];
            }
        }

        return null;
    }


    private function FinalizeTotals(array $totals, float $batteryDeltaKwh = 0.0, int $archiveID = 0, int $rangeStart = 0, int $rangeEnd = 0): array
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
        $totals['autarky'] = $totals['load'] > 0
            ? round(min(100.0, max(0.0, (($totals['load'] - $totals['gridImport']) / $totals['load']) * 100.0)), 1)
            : 0.0;

        $totals['batteryEfficiency'] = $totals['batteryCharge'] > 0
            ? round(min(100.0, max(0.0, ($totals['batteryDischarge'] / $totals['batteryCharge']) * 100.0)), 1)
            : 0.0;

        $totals['batteryDeltaKwh'] = round((float) $totals['batteryCharge'] - (float) $totals['batteryDischarge'], 2);
        $correctedOutput = $totals['batteryDischarge'] + $totals['batteryDeltaKwh'];
        $effectiveContentTs = ($rangeEnd > 0) ? min(time(), max($rangeStart, $rangeEnd - 1)) : 0;
        $totals['batteryContentNowKwh'] = ($archiveID > 0 && $effectiveContentTs > 0)
            ? $this->GetBatteryContentNowKwh($archiveID, $effectiveContentTs)
            : 0.0;
        $totals['batteryDeltaDirection'] = ($batteryDeltaKwh > 0.01) ? 'positiv' : (($batteryDeltaKwh < -0.01) ? 'negativ' : 'neutral');
        $totals['batteryCycles'] = ($archiveID > 0 && $rangeStart > 0 && $rangeEnd > 0)
            ? $this->GetBatteryCyclesDelta($archiveID, $rangeStart, $rangeEnd)
            : 0.0;
        $totals['batterySocNow'] = $this->GetBatterySocNow();

        $mode = $this->ReadAttributeString('PeriodMode');
        if ($mode === 'day') {
            $totals['batteryEfficiencyAdj'] = 0.0;
            $totals['batteryEfficiencyAdjText'] = 'Tagesansicht: siehe Speicherinhalt / Δ Inhalt';
        } else {
            $totals['batteryEfficiencyAdj'] = $totals['batteryCharge'] > 0
                ? round(min(100.0, max(0.0, ($correctedOutput / $totals['batteryCharge']) * 100.0)), 1)
                : 0.0;
            $totals['batteryEfficiencyAdjText'] = '';
        }

        return $totals;
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

    private function IsValidVar(int $id): bool
    {
        return $id > 0 && @IPS_VariableExists($id);
    }

    private function ApplySign(float $value, bool $invert): float
    {
        return $invert ? -$value : $value;
    }


    private function GetBatterySocNow(): float
    {
        $id = $this->ReadPropertyInteger('BatterySocID');
        if ($this->IsValidVar($id)) {
            return round((float) GetValue($id), 1);
        }
        return 0.0;
    }

    private function GetAggregatedSeriesRaw(int $archiveID, int $varID, int $aggregation, int $start, int $end): array
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
            $value = null;
            if (isset($row['Avg'])) {
                $value = (float) $row['Avg'];
            } elseif (isset($row['Value'])) {
                $value = (float) $row['Value'];
            }
            if ($value === null) {
                continue;
            }
            $result[$ts] = round($value, 3);
        }

        ksort($result);
        return $result;
    }



    private function GetPeakAggregationLevel(): int
    {
        $mode = $this->ReadAttributeString('PeriodMode');
        switch ($mode) {
            case 'day':
            case 'week':
                return max(0, $this->ReadPropertyInteger('SourceAggregation'));
            case 'month':
                return 1;
            case 'year':
                return 2;
            default:
                return 1;
        }
    }

    private function GetArchiveExtremeKw(int $archiveID, int $varID, int $rangeStart, int $rangeEnd, bool $invert, string $kind): array
    {
        $result = ['value' => 0.0, 'timestamp' => 0];
        if (!$this->IsValidVar($varID)) {
            return $result;
        }

        $agg = $this->GetPeakAggregationLevel();
        $rows = @AC_GetAggregatedValues($archiveID, $varID, $agg, $rangeStart, $rangeEnd, 0);
        if (!is_array($rows)) {
            return $result;
        }

        $best = null;
        $bestTs = 0;

        foreach ($rows as $row) {
            $candidate = null;
            if ($kind === 'max' && isset($row['Max'])) {
                $candidate = (float) $row['Max'];
            } elseif ($kind === 'min' && isset($row['Min'])) {
                $candidate = (float) $row['Min'];
            } elseif (isset($row['Avg'])) {
                $candidate = (float) $row['Avg'];
            }
            if ($candidate === null) {
                continue;
            }

            $candidate = $this->ApplySign($candidate, $invert);

            if ($kind === 'export' || $kind === 'charge') {
                $candidate = abs(min(0.0, $candidate));
            } elseif ($kind === 'import' || $kind === 'discharge' || $kind === 'max') {
                $candidate = max(0.0, $candidate);
            } elseif ($kind === 'min') {
                $candidate = abs(min(0.0, $candidate));
            }

            if ($best === null || $candidate > $best) {
                $best = $candidate;
                $bestTs = isset($row['TimeStamp']) ? (int)$row['TimeStamp'] : 0;
            }
        }

        if ($best !== null) {
            $result['value'] = round($best / 1000.0, 2);
            $result['timestamp'] = $bestTs;
        }

        return $result;
    }


    private function GetPeakValuesFromArchive(int $archiveID, int $rangeStart, int $rangeEnd): array
    {
        $mode = $this->ReadAttributeString('PeriodMode');
        $showTimestamp = $this->ReadPropertyBoolean('ShowPeakTimestamps') && (in_array($mode, ['day', 'week'], true) || $this->ReadPropertyBoolean('ShowPeakTimestampsLong'));

        $formatTs = function (int $ts) use ($mode): string {
            if ($ts <= 0) {
                return '';
            }
            if (in_array($mode, ['day', 'week'], true)) {
                return date('d.m H:i', $ts);
            }
            return date('d.m.Y', $ts);
        };

        $getPeak = function (int $varID, bool $invert, bool $useMin = false, bool $positiveOnly = false) use ($archiveID, $rangeStart, $rangeEnd) {
            if (!$this->IsValidVar($varID)) {
                return ['value' => 0.0, 'timestamp' => 0];
            }

            $rangeSeconds = max(1, $rangeEnd - $rangeStart);
            $aggregation = 0;
            if ($rangeSeconds > 7 * 86400 && $rangeSeconds <= 120 * 86400) {
                $aggregation = 1; // hourly
            } elseif ($rangeSeconds > 120 * 86400) {
                $aggregation = 2; // daily
            }

            if ($aggregation === 0) {
                $rows = @AC_GetLoggedValues($archiveID, $varID, $rangeStart, $rangeEnd, 10000);
            } else {
                $rows = @AC_GetAggregatedValues($archiveID, $varID, $aggregation, $rangeStart, $rangeEnd, 0);
            }
            if (!is_array($rows)) {
                $rows = [];
            }

            $bestVal = $useMin ? INF : -INF;
            $bestTs = 0;

            foreach ($rows as $row) {
                if ($aggregation === 0) {
                    if (!isset($row['Value'])) {
                        continue;
                    }
                    $val = (float) $row['Value'];
                    $ts = isset($row['TimeStamp']) ? (int) $row['TimeStamp'] : 0;
                } else {
                    if ($useMin && isset($row['Min'])) {
                        $val = (float) $row['Min'];
                    } elseif (!$useMin && isset($row['Max'])) {
                        $val = (float) $row['Max'];
                    } elseif (isset($row['Avg'])) {
                        $val = (float) $row['Avg'];
                    } else {
                        continue;
                    }
                    $ts = isset($row['TimeStamp']) ? (int) $row['TimeStamp'] : 0;
                }

                $val = $this->ApplySign($val, $invert);
                if ($positiveOnly) {
                    $val = max(0.0, $val);
                }

                if ($useMin) {
                    if ($val < $bestVal) {
                        $bestVal = $val;
                        $bestTs = $ts;
                    }
                } else {
                    if ($val > $bestVal) {
                        $bestVal = $val;
                        $bestTs = $ts;
                    }
                }
            }

            if ($bestVal === INF || $bestVal === -INF) {
                $bestVal = 0.0;
                $bestTs = 0;
            }

            return ['value' => round($bestVal / 1000.0, 2), 'timestamp' => $bestTs];
        };

        $pv = $getPeak($this->ReadPropertyInteger('PvPowerID'), $this->ReadPropertyBoolean('InvertPv'), false, true);
        $load = $getPeak($this->ReadPropertyInteger('LoadPowerID'), $this->ReadPropertyBoolean('InvertLoad'), false, true);
        $gridImport = $getPeak($this->ReadPropertyInteger('GridPowerID'), $this->ReadPropertyBoolean('InvertGrid'), false, true);
        $gridExport = $getPeak($this->ReadPropertyInteger('GridPowerID'), $this->ReadPropertyBoolean('InvertGrid'), true, false);
        $batteryCharge = $getPeak($this->ReadPropertyInteger('BatteryPowerID'), $this->ReadPropertyBoolean('InvertBattery'), true, false);
        $batteryDischarge = $getPeak($this->ReadPropertyInteger('BatteryPowerID'), $this->ReadPropertyBoolean('InvertBattery'), false, false);

        return [
            'pv' => ['value' => max(0.0, $pv['value']), 'timestamp' => $showTimestamp ? $pv['timestamp'] : 0, 'text' => $showTimestamp ? $formatTs($pv['timestamp']) : ''],
            'load' => ['value' => max(0.0, $load['value']), 'timestamp' => $showTimestamp ? $load['timestamp'] : 0, 'text' => $showTimestamp ? $formatTs($load['timestamp']) : ''],
            'gridImport' => ['value' => max(0.0, $gridImport['value']), 'timestamp' => $showTimestamp ? $gridImport['timestamp'] : 0, 'text' => $showTimestamp ? $formatTs($gridImport['timestamp']) : ''],
            'gridExport' => ['value' => abs(min(0.0, $gridExport['value'])), 'timestamp' => $showTimestamp ? $gridExport['timestamp'] : 0, 'text' => $showTimestamp ? $formatTs($gridExport['timestamp']) : ''],
            'batteryCharge' => ['value' => abs(min(0.0, $batteryCharge['value'])), 'timestamp' => $showTimestamp ? $batteryCharge['timestamp'] : 0, 'text' => $showTimestamp ? $formatTs($batteryCharge['timestamp']) : ''],
            'batteryDischarge' => ['value' => max(0.0, $batteryDischarge['value']), 'timestamp' => $showTimestamp ? $batteryDischarge['timestamp'] : 0, 'text' => $showTimestamp ? $formatTs($batteryDischarge['timestamp']) : '']
        ];
    }

    private function GetPeakValues(int $archiveID, int $rangeStart, int $rangeEnd): array
    {
        if (!$this->ReadPropertyBoolean('ShowPeakValues')) {
            return [
                'pv' => ['value' => 0.0, 'timestamp' => 0, 'text' => ''],
                'load' => ['value' => 0.0, 'timestamp' => 0, 'text' => ''],
                'gridImport' => ['value' => 0.0, 'timestamp' => 0, 'text' => ''],
                'gridExport' => ['value' => 0.0, 'timestamp' => 0, 'text' => ''],
                'batteryCharge' => ['value' => 0.0, 'timestamp' => 0, 'text' => ''],
                'batteryDischarge' => ['value' => 0.0, 'timestamp' => 0, 'text' => '']
            ];
        }
        return $this->GetPeakValuesFromArchive($archiveID, $rangeStart, $rangeEnd);
    }


    private function GetVisibleChartPeakMarkers(array $data, array $peakValues = []): array
    {
        $result = [
            'pv' => ['index' => null, 'value' => 0.0, 'label' => 'PV Max'],
            'load' => ['index' => null, 'value' => 0.0, 'label' => 'Verbrauch Max']
        ];

        if (!$this->ReadPropertyBoolean('ShowPeakMarkersPvLoad')) {
            return $result;
        }

        foreach (['pv' => 'pv', 'load' => 'load'] as $target => $key) {
            if (!isset($data[$key]) || !is_array($data[$key]) || count($data[$key]) === 0) {
                continue;
            }
            $vals = array_map('floatval', $data[$key]);
            $maxVal = max($vals);
            $maxIdx = array_search($maxVal, $vals, true);
            if ($maxIdx !== false) {
                $result[$target]['index'] = (int) $maxIdx;
                if (isset($peakValues[$target]) && is_array($peakValues[$target])) {
                    $result[$target]['value'] = round((float) ($peakValues[$target]['value'] ?? 0.0), 2);
                } else {
                    $result[$target]['value'] = round((float) $maxVal, 2);
                }
            }
        }

        return $result;
    }

    private function FormatPeakValue($peak): string
    {
        if (is_array($peak)) {
            $value = $this->Fmt((float) ($peak['value'] ?? 0.0)) . ' kW';
            if ($this->ReadPropertyBoolean('ShowPeakTimestamps') && !empty($peak['text'])) {
                $value .= ' · ' . $peak['text'];
            }
            return $value;
        }
        return $this->Fmt((float) $peak) . ' kW';
    }

    private function GetTargetComparisonTotals(int $archiveID, int $rangeStart, int $rangeEnd, array $totals): array
    {
        $result = [
            'enabled' => $this->ReadPropertyBoolean('EnableTargetComparison'),
            'target' => 0.0,
            'actual' => round((float) ($totals['pv'] ?? 0.0), 2),
            'delta' => 0.0,
            'percent' => 0.0
        ];

        if (!$result['enabled']) {
            return $result;
        }

        $target = 0.0;
        $found = false;

        $dayId = $this->ReadPropertyInteger('PvTargetDayID');
        if ($this->IsValidVar($dayId)) {
            for ($day = strtotime(date('Y-m-d 00:00:00', $rangeStart)); $day < $rangeEnd; $day = strtotime('+1 day', $day)) {
                $val = $this->ReadDayValueFromDailyHistory($archiveID, $dayId, $day);
                if ($val !== null) {
                    $target += (float) $val;
                    $found = true;
                }
            }
        }

        if (!$found) {
            $totalId = $this->ReadPropertyInteger('PvTargetTotalID');
            if ($this->IsValidVar($totalId)) {
                $diff = $this->ReadCounterDiffForDay($archiveID, $totalId, $rangeStart, $rangeEnd);
                if ($diff !== null) {
                    $target = (float) $diff;
                    $found = true;
                }
            }
        }

        if (!$found) {
            return $result;
        }

        $result['target'] = round(max(0.0, $target), 2);
        $result['delta'] = round($result['actual'] - $result['target'], 2);
        $result['percent'] = $result['target'] > 0
            ? round(($result['actual'] / $result['target']) * 100.0, 1)
            : 0.0;

        return $result;
    }

    private function BuildPowerSeries(int $archiveID, int $start, int $end, int $aggregation): array
    {
        $aligned = $this->AlignSeriesByTimestamp([
            'pv' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('PvPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertPv')),
            'grid' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('GridPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertGrid')),
            'load' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('LoadPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertLoad')),
            'battery' => $this->GetAggregatedSeriesKw($archiveID, $this->ReadPropertyInteger('BatteryPowerID'), $aggregation, $start, $end, $this->ReadPropertyBoolean('InvertBattery'))
        ]);
        return $aligned;
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
            $value = null;
            if (isset($row['Avg'])) {
                $value = (float) $row['Avg'];
            } elseif (isset($row['Value'])) {
                $value = (float) $row['Value'];
            }
            if ($value === null) {
                continue;
            }
            $result[$ts] = round($this->ApplySign($value, $invert) / 1000.0, 3);
        }

        ksort($result);
        return $result;
    }

    private function RelabelAlignedSeriesForMode(array $aligned, string $mode): array
    {
        if (!isset($aligned['timestamps'])) {
            return $aligned;
        }

        $labels = [];
        foreach ($aligned['timestamps'] as $ts) {
            if ($mode === 'day') {
                $labels[] = date('H:i', (int) $ts);
            } else {
                $labels[] = date('d.m H:i', (int) $ts);
            }
        }
        $aligned['labels'] = $labels;
        return $aligned;
    }

    private function BuildSourceChartData(int $archiveID, int $start, int $end): array
    {
        $mode = $this->ReadAttributeString('PeriodMode');
        $viewMode = $this->GetConfiguredViewMode($mode, 'sources');

        if ($viewMode === 'hours') {
            $aggregation = ($mode === 'day' || $mode === 'week') ? $this->ReadPropertyInteger('SourceAggregation') : 0;
            $aligned = $this->BuildPowerSeries($archiveID, $start, $end, $aggregation);
            $aligned = $this->RelabelAlignedSeriesForMode($aligned, $mode);
            $aligned = $this->ReduceAlignedSeries($aligned, max(24, $this->ReadPropertyInteger('MaxSourcePoints')));
            $aligned['unit'] = 'kW';
            $aligned['chartType'] = 'line';
            $aligned['soc'] = [];

            if ($mode === 'day' && $this->ReadPropertyBoolean('ShowSocOverlay') && $this->IsValidVar($this->ReadPropertyInteger('BatterySocID'))) {
                $socRows = $this->GetAggregatedSeriesRaw(
                    $archiveID,
                    $this->ReadPropertyInteger('BatterySocID'),
                    $aggregation,
                    $start,
                    $end
                );

                $socValues = [];
                $lastSoc = 0.0;
                foreach ($aligned['timestamps'] as $ts) {
                    if (isset($socRows[$ts])) {
                        $lastSoc = (float) $socRows[$ts];
                    } else {
                        foreach ($socRows as $socTs => $socVal) {
                            if ($socTs <= $ts) {
                                $lastSoc = (float) $socVal;
                            } else {
                                break;
                            }
                        }
                    }
                    $socValues[] = round($lastSoc, 2);
                }
                $aligned['soc'] = $socValues;
            }

            return $aligned;
        }

        $rows = $this->BuildPeriodEnergyRows($archiveID, $start, $end, $mode, $viewMode);
        $series = ['labels' => [], 'pv' => [], 'grid' => [], 'load' => [], 'battery' => [], 'soc' => [], 'unit' => 'kWh', 'chartType' => 'line'];
        foreach ($rows as $row) {
            $series['labels'][] = $row['label'];
            $series['pv'][] = round((float) $row['pv'], 2);
            $series['grid'][] = round((float) $row['grid'], 2);
            $series['load'][] = round((float) $row['load'], 2);
            $series['battery'][] = round((float) $row['battery'], 2);
        }
        return $series;
    }

    private function BuildUsageChartData(int $archiveID, int $start, int $end): array
    {
        $mode = $this->ReadAttributeString('PeriodMode');
        $viewMode = $this->GetConfiguredViewMode($mode, 'usage');

        if ($viewMode === 'hours') {
            $aggregation = ($mode === 'day' || $mode === 'week') ? $this->ReadPropertyInteger('UsageAggregation') : 0;
            $aligned = $this->BuildPowerSeries($archiveID, $start, $end, $aggregation);
            $buckets = [];
            $count = count($aligned['timestamps']);
            if ($count < 2) {
                return [];
            }

            for ($i = 1; $i < $count; $i++) {
                $from = (int) $aligned['timestamps'][$i - 1];
                $to = (int) $aligned['timestamps'][$i];
                $dtHours = max(1 / 60, ($to - $from) / 3600.0);

                $pv = max(0.0, (float) $aligned['pv'][$i - 1]);
                $grid = (float) $aligned['grid'][$i - 1];
                $load = max(0.0, (float) $aligned['load'][$i - 1]);
                $battery = (float) $aligned['battery'][$i - 1];

                $buckets[] = [
                    'label' => ($mode === 'day') ? date('H:i', $from) : date('d.m H:i', $from),
                    'pvToLoad' => round(min($pv, $load) * $dtHours, 3),
                    'gridImport' => round(max(0.0, $grid) * $dtHours, 3),
                    'batteryCharge' => round(max(0.0, -$battery) * $dtHours, 3),
                    'batteryDischarge' => round(max(0.0, $battery) * $dtHours, 3),
                    'gridExport' => round(max(0.0, -$grid) * $dtHours, 3)
                ];
            }

            return $this->ReduceUsageBuckets($buckets, max(8, $this->ReadPropertyInteger('MaxUsagePoints')));
        }

        $rows = $this->BuildPeriodUsageRows($archiveID, $start, $end, $mode, $viewMode);
        return $this->ReduceUsageBuckets($rows, max(8, $this->ReadPropertyInteger('MaxUsagePoints')));
    }

    private function BuildPeriodEnergyRows(int $archiveID, int $rangeStart, int $rangeEnd, string $mode, string $viewMode): array
    {
        $daily = [];
        for ($day = strtotime(date('Y-m-d 00:00:00', $rangeStart)); $day < $rangeEnd; $day = strtotime('+1 day', $day)) {
            $vals = $this->ResolveSingleDayTotals($archiveID, $day);
            $daily[] = [
                'ts' => $day,
                'label' => date('d.m', $day),
                'pv' => (float) $vals['pv'],
                'grid' => (float) $vals['gridImport'] - (float) $vals['gridExport'],
                'load' => (float) $vals['load'],
                'battery' => (float) $vals['batteryCharge'] + (float) $vals['batteryDischarge']
            ];
        }

        if (($mode === 'week' && $viewMode === 'days') || ($mode === 'month' && $viewMode === 'days')) {
            return $daily;
        }

        if ($mode === 'month' && $viewMode === 'weeks') {
            return $this->AggregateEnergyByWeeks($daily);
        }

        if ($mode === 'year' && $viewMode === 'weeks') {
            return $this->AggregateEnergyByWeeks($daily);
        }

        if ($mode === 'year' && $viewMode === 'months') {
            return $this->AggregateEnergyByMonths($daily);
        }

        return $daily;
    }

    private function BuildPeriodUsageRows(int $archiveID, int $rangeStart, int $rangeEnd, string $mode, string $viewMode): array
    {
        $rows = [];
        for ($day = strtotime(date('Y-m-d 00:00:00', $rangeStart)); $day < $rangeEnd; $day = strtotime('+1 day', $day)) {
            $vals = $this->ResolveSingleDayTotals($archiveID, $day);
            $rows[] = [
                'ts' => $day,
                'label' => date('d.m', $day),
                'pvToLoad' => round(min((float) $vals['pv'], (float) $vals['load']), 3),
                'gridImport' => round((float) $vals['gridImport'], 3),
                'batteryCharge' => round((float) $vals['batteryCharge'], 3),
                'batteryDischarge' => round((float) $vals['batteryDischarge'], 3),
                'gridExport' => round((float) $vals['gridExport'], 3)
            ];
        }

        if (($mode === 'week' && $viewMode === 'days') || ($mode === 'month' && $viewMode === 'days')) {
            return $rows;
        }

        if ($mode === 'month' && $viewMode === 'weeks') {
            return $this->AggregateUsageByWeeks($rows);
        }

        if ($mode === 'year' && $viewMode === 'weeks') {
            return $this->AggregateUsageByWeeks($rows);
        }

        if ($mode === 'year' && $viewMode === 'months') {
            return $this->AggregateUsageByMonths($rows);
        }

        return $rows;
    }

    private function AggregateEnergyByWeeks(array $rows): array
    {
        $weeks = [];
        foreach ($rows as $row) {
            $key = date('o-W', $row['ts']);
            if (!isset($weeks[$key])) {
                $weeks[$key] = ['label' => 'KW ' . date('W', $row['ts']), 'pv' => 0.0, 'grid' => 0.0, 'load' => 0.0, 'battery' => 0.0];
            }
            foreach (['pv', 'grid', 'load', 'battery'] as $k) {
                $weeks[$key][$k] += (float) $row[$k];
            }
        }
        foreach ($weeks as &$r) {
            foreach (['pv', 'grid', 'load', 'battery'] as $k) {
                $r[$k] = round($r[$k], 2);
            }
        }
        unset($r);
        return array_values($weeks);
    }

    private function AggregateEnergyByMonths(array $rows): array
    {
        $months = [];
        foreach ($rows as $row) {
            $key = date('Y-m', $row['ts']);
            if (!isset($months[$key])) {
                $months[$key] = ['label' => date('M', $row['ts']), 'pv' => 0.0, 'grid' => 0.0, 'load' => 0.0, 'battery' => 0.0];
            }
            foreach (['pv', 'grid', 'load', 'battery'] as $k) {
                $months[$key][$k] += (float) $row[$k];
            }
        }
        foreach ($months as &$r) {
            foreach (['pv', 'grid', 'load', 'battery'] as $k) {
                $r[$k] = round($r[$k], 2);
            }
        }
        unset($r);
        return array_values($months);
    }

    private function AggregateUsageByWeeks(array $rows): array
    {
        $weeks = [];
        foreach ($rows as $row) {
            $key = date('o-W', $row['ts']);
            if (!isset($weeks[$key])) {
                $weeks[$key] = ['label' => 'KW ' . date('W', $row['ts']), 'pvToLoad' => 0.0, 'gridImport' => 0.0, 'batteryCharge' => 0.0, 'batteryDischarge' => 0.0, 'gridExport' => 0.0];
            }
            foreach (['pvToLoad', 'gridImport', 'batteryCharge', 'batteryDischarge', 'gridExport'] as $k) {
                $weeks[$key][$k] += (float) $row[$k];
            }
        }
        foreach ($weeks as &$r) {
            foreach (['pvToLoad', 'gridImport', 'batteryCharge', 'batteryDischarge', 'gridExport'] as $k) {
                $r[$k] = round($r[$k], 3);
            }
        }
        unset($r);
        return array_values($weeks);
    }

    private function AggregateUsageByMonths(array $rows): array
    {
        $months = [];
        foreach ($rows as $row) {
            $key = date('Y-m', $row['ts']);
            if (!isset($months[$key])) {
                $months[$key] = ['label' => date('M', $row['ts']), 'pvToLoad' => 0.0, 'gridImport' => 0.0, 'batteryCharge' => 0.0, 'batteryDischarge' => 0.0, 'gridExport' => 0.0];
            }
            foreach (['pvToLoad', 'gridImport', 'batteryCharge', 'batteryDischarge', 'gridExport'] as $k) {
                $months[$key][$k] += (float) $row[$k];
            }
        }
        foreach ($months as &$r) {
            foreach (['pvToLoad', 'gridImport', 'batteryCharge', 'batteryDischarge', 'gridExport'] as $k) {
                $r[$k] = round($r[$k], 3);
            }
        }
        unset($r);
        return array_values($months);
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
            'labels' => [],
            'pv' => [],
            'grid' => [],
            'load' => [],
            'battery' => []
        ];

        $last = [
            'pv' => 0.0,
            'grid' => 0.0,
            'load' => 0.0,
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
            'labels' => [],
            'pv' => [],
            'grid' => [],
            'load' => [],
            'battery' => []
        ];

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
        if ($count <= $maxPoints) {
            return $rows;
        }

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

    private function CalculateTotalsFromPowerSeries(array $sourceChart): array
    {
        $pvEnergy = 0.0;
        $gridImport = 0.0;
        $gridExport = 0.0;
        $loadEnergy = 0.0;
        $batteryCharge = 0.0;
        $batteryDischarge = 0.0;

        $count = count($sourceChart['timestamps'] ?? []);
        if ($count < 2) {
            return [
                'pv' => 0.0,
                'gridImport' => 0.0,
                'gridExport' => 0.0,
                'load' => 0.0,
                'batteryCharge' => 0.0,
                'batteryDischarge' => 0.0
            ];
        }

        for ($i = 1; $i < $count; $i++) {
            $from = (int) $sourceChart['timestamps'][$i - 1];
            $to = (int) $sourceChart['timestamps'][$i];
            $dtHours = max(1 / 60, ($to - $from) / 3600.0);

            $pv = max(0.0, (float) $sourceChart['pv'][$i - 1]);
            $grid = (float) $sourceChart['grid'][$i - 1];
            $load = max(0.0, (float) $sourceChart['load'][$i - 1]);
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

        return [
            'pv' => round($pvEnergy, 2),
            'gridImport' => round($gridImport, 2),
            'gridExport' => round($gridExport, 2),
            'load' => round($loadEnergy, 2),
            'batteryCharge' => round($batteryCharge, 2),
            'batteryDischarge' => round($batteryDischarge, 2)
        ];
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

    private function GetOverviewHtml(array $t, array $targetComparison = [], array $peakValues = []): string
    {
        $theme = $this->GetThemeConfig();
        $mode = $this->ReadAttributeString('PeriodMode');
        $batteryExtra = '';

        if ($mode === 'day') {
            $batteryExtra = $this->OverviewBox('Speicherstand aktuell', $this->Fmt((float) $t['batteryContentNowKwh']) . ' kWh')
                . $this->OverviewBox('SoC aktuell', $this->Fmt((float) $t['batterySocNow']) . ' %')
                . $this->OverviewBox('Δ Batt.-Inhalt', $this->Fmt((float) $t['batteryDeltaKwh']) . ' kWh');
        } else {
            $batteryExtra = $this->OverviewBox('Δ Batt.-Inhalt', $this->Fmt((float) $t['batteryDeltaKwh']) . ' kWh')
                . $this->OverviewBox('SoC-bereinigt', $this->Fmt((float) $t['batteryEfficiencyAdj']) . ' %')
                . $this->OverviewBox('Wirkungsgrad', $this->Fmt((float) $t['batteryEfficiency']) . ' %');
        }

        $peakHtml = '';
        if ($this->ReadPropertyBoolean('ShowPeakValues')) {
            $peakHtml = '<div><div class="edb-section">Peak-Werte</div><div class="edb-grid">'
                . $this->OverviewBox('PV', $this->FormatPeakValue($peakValues['pv'] ?? []))
                . $this->OverviewBox('Verbrauch', $this->FormatPeakValue($peakValues['load'] ?? []))
                . $this->OverviewBox('Netz Bezug max', $this->FormatPeakValue($peakValues['gridImport'] ?? []))
                . $this->OverviewBox('Netz Lieferung max', $this->FormatPeakValue($peakValues['gridExport'] ?? []))
                . $this->OverviewBox('Batt. Laden max', $this->FormatPeakValue($peakValues['batteryCharge'] ?? []))
                . $this->OverviewBox('Batt. Entladen max', $this->FormatPeakValue($peakValues['batteryDischarge'] ?? []))
                . '</div></div>';
        }

        $targetHtml = '';
        if (($targetComparison['enabled'] ?? false) && ((float) ($targetComparison['target'] ?? 0.0)) > 0) {
            $targetHtml = '<div><div class="edb-section">Soll / Ist Vergleich</div><div class="edb-grid">'
                . $this->OverviewBox('Soll', $this->Fmt((float) $targetComparison['target']) . ' kWh')
                . $this->OverviewBox('Ist', $this->Fmt((float) $targetComparison['actual']) . ' kWh')
                . $this->OverviewBox('Abweichung', $this->Fmt((float) $targetComparison['delta']) . ' kWh')
                . $this->OverviewBox('Erfüllung', $this->Fmt((float) $targetComparison['percent']) . ' %')
                . '</div></div>';
        }

        $bottomHtml = '';
        if ($peakHtml !== '' || $targetHtml !== '') {
            $bottomHtml = '<div class="edb-2col" style="margin-top:12px;">' . $peakHtml . $targetHtml . '</div>';
        }

        $balance = round((float) ($t['pv'] ?? 0.0) - (float) ($t['load'] ?? 0.0), 2);
        $badgeHtml = '';
        if ($this->ReadPropertyBoolean('ShowBalanceBadge')) {
            $badgeBg = $theme['card'];
            $badgeText = $theme['text'];
            $badgeBorder = $theme['border'];

            if ($this->ReadPropertyBoolean('ColorBalanceBadgeByState')) {
                if ($balance > 0.001) {
                    $badgeBg = ($theme['mode'] === 'dark' || $theme['bg'] === 'transparent') ? 'rgba(76,175,80,0.18)' : 'rgba(76,175,80,0.14)';
                    $badgeText = '#4caf50';
                    $badgeBorder = 'rgba(76,175,80,0.35)';
                } elseif ($balance < -0.001) {
                    $badgeBg = ($theme['mode'] === 'dark' || $theme['bg'] === 'transparent') ? 'rgba(244,67,54,0.18)' : 'rgba(244,67,54,0.12)';
                    $badgeText = '#f44336';
                    $badgeBorder = 'rgba(244,67,54,0.35)';
                }
            } else {
                if ($theme['mode'] === 'dark' || $theme['bg'] === 'transparent') {
                    $badgeBg = 'rgba(255,255,255,0.08)';
                    $badgeText = '#ffffff';
                    $badgeBorder = 'rgba(255,255,255,0.16)';
                } else {
                    $badgeBg = '#ffffff';
                    $badgeText = '#333333';
                    $badgeBorder = $theme['border'];
                }
            }

            $prefix = ($balance > 0) ? '+' : '';
            $badgeHtml = '<div class="edb-badge" style="background:' . $badgeBg . ';color:' . $badgeText . ';border-color:' . $badgeBorder . ';">'
                . $prefix . $this->Fmt($balance) . ' kWh'
                . '</div>';
        }

        return '<div style="font-family:Arial,sans-serif;padding:12px;color:' . $theme['text'] . ';background:' . $theme['bg'] . ';">'
            . '<style>.edb-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}.edb-2col{display:grid;grid-template-columns:1fr 1fr;gap:12px}.edb-card{background:' . $theme['bg'] . ';border:1px solid ' . $theme['border'] . ';border-radius:18px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.edb-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}.edb-title{font-size:24px;font-weight:700}.edb-badge{font-size:18px;font-weight:700;padding:10px 14px;color:' . $theme['text'] . ';border:1px solid ' . $theme['border'] . ';border-radius:14px}.edb-box{background:' . $theme['card'] . ';border:1px solid ' . $theme['border'] . ';border-radius:12px;padding:12px}.edb-label{font-size:12px;color:' . $theme['muted'] . ';margin-bottom:4px}.edb-value{font-size:20px;font-weight:700}.edb-section{font-size:14px;font-weight:700;color:' . $theme['muted'] . ';margin-bottom:6px}</style>'
            . '<div class="edb-card">'
            . '<div class="edb-head"><div class="edb-title">Verbrauchsübersicht</div>' . $badgeHtml . '</div>'
            . '<div class="edb-grid">'
            . $this->OverviewBox('PV', $this->Fmt((float) $t['pv']) . ' kWh')
            . $this->OverviewBox('Bezug', $this->Fmt((float) $t['gridImport']) . ' kWh')
            . $this->OverviewBox('Einspeisung', $this->Fmt((float) $t['gridExport']) . ' kWh')
            . $this->OverviewBox('Verbrauch', $this->Fmt((float) $t['load']) . ' kWh')
            . '</div>'
            . '<div class="edb-2col" style="margin-top:12px;">'
            . '<div><div class="edb-section">Eigenverbrauch & Autarkie</div><div class="edb-grid">'
            . $this->OverviewBox('Eigenverbrauch', $this->Fmt((float) $t['selfConsumption']) . ' kWh')
            . $this->OverviewBox('Autarkie', $this->Fmt((float) $t['autarky']) . ' %')
            . '</div></div>'
            . '<div><div class="edb-section">Batterie-Analyse</div><div class="edb-grid">'
            . $this->OverviewBox('Batt. Laden', $this->Fmt((float) $t['batteryCharge']) . ' kWh')
            . $this->OverviewBox('Batt. Entladen', $this->Fmt((float) $t['batteryDischarge']) . ' kWh')
            . $batteryExtra
            . '</div></div>'
            . '</div>'
            . $bottomHtml
            . '</div></div>';
    }

    private function OverviewBox(string $label, string $value): string
    {
        return '<div class="edb-box"><div class="edb-label">' . htmlspecialchars($label) . '</div><div class="edb-value">' . htmlspecialchars($value) . '</div></div>';
    }





    private function NormalizeHexColor(string $value, string $fallback): string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return $value;
        }
        return $fallback;
    }

    private function HexToRgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return 'rgba(0,0,0,' . $alpha . ')';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . $alpha . ')';
    }

    private function GetThemeConfig(): array
    {
        $preset = $this->ReadPropertyString('ThemePreset');

        if ($preset === 'dark') {
            $mode = 'dark';
            $bg = '#121212';
            $card = '#1f1f1f';
            $text = '#f2f2f2';
            $muted = '#bdbdbd';
        } elseif ($preset === 'transparent') {
            $mode = 'dark';
            $bg = 'transparent';
            $card = '#1f1f1f';
            $text = '#f2f2f2';
            $muted = '#bdbdbd';
        } else {
            $mode = 'light';
            $bg = '#f7f7f7';
            $card = '#ffffff';
            $text = '#222222';
            $muted = '#666666';
        }

        return [
            'mode' => $mode,
            'bg' => $bg,
            'card' => $card,
            'text' => $text,
            'muted' => $muted,
            'border' => ($mode === 'dark') ? '#444' : '#ddd',
            'pv' => '#ff9800',
            'grid' => '#f44336',
            'battery' => '#2196f3',
            'house' => '#4caf50',
            'soc' => '#4caf50',
            'pvFill' => 'rgba(255,152,0,0.2)',
            'gridFill' => 'rgba(244,67,54,0.15)',
            'batteryFill' => 'rgba(33,150,243,0.15)',
            'socFill' => 'rgba(76,175,80,0.0)'
        ];
    }

    private function GetConfiguredLivePowerVarId(string $kind): int
    {
        $map = [
            'pv' => ['SankeyLivePvID', 'PvPowerID'],
            'grid' => ['SankeyLiveGridID', 'GridPowerID'],
            'load' => ['SankeyLiveLoadID', 'LoadPowerID'],
            'battery' => ['SankeyLiveBatteryID', 'BatteryPowerID']
        ];
        $props = $map[$kind] ?? null;
        if ($props === null) {
            return 0;
        }
        $preferred = $this->ReadPropertyInteger($props[0]);
        if ($preferred > 0) {
            return $preferred;
        }
        return $this->ReadPropertyInteger($props[1]);
    }

    private function GetCurrentPowerValueKw(int $varID, bool $invert): float
    {
        if (!$this->IsValidVar($varID)) {
            return 0.0;
        }
        return round($this->ApplySign((float) GetValue($varID), $invert) / 1000.0, 3);
    }

    private function GetSankeyLiveFlowsKw(): array
    {
        $pv = max(0.0, $this->GetCurrentPowerValueKw($this->GetConfiguredLivePowerVarId('pv'), $this->ReadPropertyBoolean('InvertPv')));
        $grid = $this->GetCurrentPowerValueKw($this->GetConfiguredLivePowerVarId('grid'), $this->ReadPropertyBoolean('InvertGrid'));
        $load = max(0.0, $this->GetCurrentPowerValueKw($this->GetConfiguredLivePowerVarId('load'), $this->ReadPropertyBoolean('InvertLoad')));
        $battery = $this->GetCurrentPowerValueKw($this->GetConfiguredLivePowerVarId('battery'), $this->ReadPropertyBoolean('InvertBattery'));

        $gridImport = max(0.0, $grid);
        $gridExport = max(0.0, -$grid);
        $battCharge = max(0.0, -$battery);
        $battDischarge = max(0.0, $battery);

        $pvRemaining = max(0.0, $pv - $gridExport);
        $pvToBattery = min($battCharge, $pvRemaining);
        $pvRemaining -= $pvToBattery;

        $pvToLoad = min($load, $pvRemaining);
        $remainingLoad = max(0.0, $load - $pvToLoad);

        $batteryToLoad = min($battDischarge, $remainingLoad);
        $remainingLoad -= $batteryToLoad;

        $gridToLoad = min($gridImport, $remainingLoad);

        return [
            ['PV', 'Haus', round($pvToLoad, 3)],
            ['PV', 'Batterie', round($pvToBattery, 3)],
            ['PV', 'Netz', round($gridExport, 3)],
            ['Netz', 'Haus', round($gridToLoad, 3)],
            ['Batterie', 'Haus', round($batteryToLoad, 3)]
        ];
    }

    private function GetSankeyTooltipMap(array $flows, float $total, string $unit): array
    {
        $map = [];
        foreach ($flows as $row) {
            $key = $row[0] . '|' . $row[1];
            $value = (float) $row[2];
            $pct = ($total > 0) ? round(($value / $total) * 100.0, 1) : 0.0;
            $map[$key] = [
                'value' => $value,
                'percent' => $pct,
                'unit' => $unit
            ];
        }
        return $map;
    }

    private function GetSankeyFlows(array $t): array
    {
        $pv = max(0.0, (float) ($t['pv'] ?? 0.0));
        $gridImport = max(0.0, (float) ($t['gridImport'] ?? 0.0));
        $gridExport = max(0.0, (float) ($t['gridExport'] ?? 0.0));
        $load = max(0.0, (float) ($t['load'] ?? 0.0));
        $battCharge = max(0.0, (float) ($t['batteryCharge'] ?? 0.0));
        $battDischarge = max(0.0, (float) ($t['batteryDischarge'] ?? 0.0));

        // Näherungsweise Verteilung für Sankey
        $pvRemaining = max(0.0, $pv - $gridExport);
        $pvToBattery = min($battCharge, $pvRemaining);
        $pvRemaining -= $pvToBattery;

        $pvToLoad = min($load, $pvRemaining);
        $remainingLoad = max(0.0, $load - $pvToLoad);

        $batteryToLoad = min($battDischarge, $remainingLoad);
        $remainingLoad -= $batteryToLoad;

        $gridToLoad = min($gridImport, $remainingLoad);

        return [
            ['PV', 'Haus', round($pvToLoad, 2)],
            ['PV', 'Batterie', round($pvToBattery, 2)],
            ['PV', 'Netz', round($gridExport, 2)],
            ['Netz', 'Haus', round($gridToLoad, 2)],
            ['Batterie', 'Haus', round($batteryToLoad, 2)]
        ];
    }

    private function GetSankeyHtml(array $totals, string $label, bool $liveMode = false): string
    {
        if (($liveMode && !$this->ReadPropertyBoolean('ShowSankeyLive')) || (!$liveMode && !$this->ReadPropertyBoolean('ShowSankeyPeriod'))) {
            return '';
        }

        $theme = $this->GetThemeConfig();
        $showPercentages = $this->ReadPropertyBoolean('SankeyShowPercentages');

        if ($liveMode) {
            $flows = array_values(array_filter($this->GetSankeyLiveFlowsKw(), function ($row) { return (float) $row[2] > 0.001; }));
            $unit = 'kW';
            $title = 'Energiefluss Live';
        } else {
            $flows = array_values(array_filter($this->GetSankeyFlows($totals), function ($row) { return (float) $row[2] > 0.01; }));
            $unit = 'kWh';
            $title = 'Energiefluss Zeitraum';
        }

        $total = 0.0;
        foreach ($flows as $row) { $total += (float) $row[2]; }

        $tooltipMap = $this->GetSankeyTooltipMap($flows, $total, $unit);
        $json = json_encode($flows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tooltipJson = json_encode($tooltipMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $themeJson = json_encode($theme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $valuesHtml = '';
        foreach ($flows as $row) {
            $valuesHtml .= '<span style="margin-right:12px;"><b>' . $row[0] . '→' . $row[1] . ':</b> ' . number_format($row[2], 2, ',', '.') . ' ' . $unit . '</span>';
        }

        $containerId = $liveMode ? 'edbSankeyChartLive' : 'edbSankeyChartKwh';
        $tipId = $liveMode ? 'edbSankeyTipLive' : 'edbSankeyTipKwh';
        $refresh = max(5, (int)$this->ReadPropertyInteger('SankeyLiveRefresh')) * 1000;
        $liveFlag = $liveMode ? 'true' : 'false';

        return '<div style="font-family:Arial,sans-serif;padding:12px;color:' . $theme['text'] . ';background:' . $theme['bg'] . ';">'
            . '<style>.edb-card{background:' . $theme['bg'] . ';border:1px solid ' . $theme['border'] . ';border-radius:18px;padding:16px}.edb-wrap{position:relative;height:300px}.edb-tip{position:absolute;display:none;background:rgba(33,33,33,.96);color:#fff;padding:8px;border-radius:8px;font-size:12px}.edb-sub{font-size:12px;color:' . $theme['muted'] . ';margin-bottom:10px}</style>'
            . '<div class="edb-card"><div style="font-size:20px;font-weight:bold;margin-bottom:6px;">' . $title . '</div><div class="edb-sub">' . htmlspecialchars($label) . '</div><div style="margin-bottom:10px;">' . $valuesHtml . '</div><div class="edb-wrap"><div id="' . $containerId . '" style="width:100%;height:100%;"></div><div id="' . $tipId . '" class="edb-tip"></div></div></div>'
            . '<script src="https://www.gstatic.com/charts/loader.js"></script>'
            . '<script>(function(){var rows=' . $json . ';var tooltipMap=' . $tooltipJson . ';var theme=' . $themeJson . ';var showPct=' . ($showPercentages ? 'true' : 'false') . ';var liveMode=' . $liveFlag . ';google.charts.load("current",{packages:["sankey"]});google.charts.setOnLoadCallback(draw);function draw(){var data=new google.visualization.DataTable();data.addColumn("string","Von");data.addColumn("string","Nach");data.addColumn("number","Wert");data.addRows(rows);var el=document.getElementById("' . $containerId . '");if(!el){return;}var chart=new google.visualization.Sankey(el);chart.draw(data,{height:300,tooltip:{trigger:"none"},sankey:{node:{colors:[theme.pv,theme.house,theme.battery,theme.grid]},link:{colorMode:"gradient",colors:[theme.pv,theme.house,theme.battery,theme.grid]}}});google.visualization.events.addListener(chart,"onmouseover",function(e){if(typeof e.row!=="number"||e.row<0||e.row>=rows.length)return;var from=data.getValue(e.row,0);var to=data.getValue(e.row,1);var meta=tooltipMap[from+"|"+to];if(!meta)return;var tip=document.getElementById("' . $tipId . '");var html="<b>"+from+"→"+to+"</b><br>"+meta.value.toFixed(2)+" "+meta.unit;if(showPct){html+="<br>"+meta.percent.toFixed(1)+" %";}tip.innerHTML=html;tip.style.display="block";});google.visualization.events.addListener(chart,"onmouseout",function(){document.getElementById("' . $tipId . '").style.display="none";});el.addEventListener("mousemove",function(ev){var tip=document.getElementById("' . $tipId . '");tip.style.left=(ev.offsetX+14)+"px";tip.style.top=(ev.offsetY+14)+"px";});}if(liveMode){setInterval(draw,' . $refresh . ');} })();</script>'
            . '</div>';
    }

    private function GetSourcesHtml(array $data, string $label, array $peakValues = []): string
    {
        $theme = $this->GetThemeConfig();
        $unit = $data['unit'] ?? 'kW';
        $chartType = $data['chartType'] ?? 'line';
        $chartPayload = [
            'labels' => $data['labels'],
            'pv' => $data['pv'],
            'grid' => $data['grid'],
            'load' => $data['load'],
            'battery' => $data['battery'],
            'soc' => $data['soc'] ?? []
        ];
        $json = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $themeJson = json_encode($theme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $markerJson = json_encode($this->GetVisibleChartPeakMarkers($chartPayload, $peakValues), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $showMarkers = $this->ReadPropertyBoolean('ShowPeakMarkersPvLoad') ? 'true' : 'false';
        $enableHoverDebug = $this->ReadPropertyBoolean('EnablePeakHoverDebug') ? 'true' : 'false';
        $height = max(220, min(420, 220 + (int) floor(count($data['labels']) / 4)));
        $labelEsc = htmlspecialchars($label);
        $unitEsc = htmlspecialchars($unit);
        $typeEsc = htmlspecialchars($chartType);

        return '<div style="font-family:Arial,sans-serif;padding:12px;color:' . $theme['text'] . ';background:' . $theme['bg'] . ';">'
            . '<style>.edb-card{background:' . $theme['bg'] . ';border:1px solid ' . $theme['border'] . ';border-radius:18px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.edb-title{font-size:24px;font-weight:700;margin-bottom:2px}.edb-sub{font-size:13px;color:' . $theme['muted'] . ';margin-bottom:8px}.edb-wrap{position:relative;height:' . $height . 'px}</style>'
            . '<div class="edb-card"><div class="edb-title">Stromquellen</div><div class="edb-sub">' . $labelEsc . '</div><div class="edb-wrap"><canvas id="edbSourceChart"></canvas></div></div>'
            . '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>'
            . '<script>(function(){'
            . 'const d=' . $json . ';'
            . 'const theme=' . $themeJson . ';'
            . 'const markers=' . $markerJson . ';'
            . 'const showMarkers=' . $showMarkers . ';'
            . 'const enableHoverDebug=' . $enableHoverDebug . ';'
            . 'const hoverState={key:null};'
            . 'const datasets=['
            . '{label:"PV",data:d.pv,borderColor:theme.pv,backgroundColor:theme.pvFill,fill:false,tension:.25,pointRadius:0,borderWidth:2},'
            . '{label:"Netz",data:d.grid,borderColor:theme.grid,backgroundColor:theme.gridFill,fill:false,tension:.2,pointRadius:0,borderWidth:2},'
            . '{label:"Verbrauch",data:d.load,borderColor:(theme.mode==="dark" ? "#e0e0e0" : "#000000"),backgroundColor:(theme.mode==="dark" ? "rgba(224,224,224,.12)" : "rgba(0,0,0,.10)"),borderDash:[6,4],tension:.15,pointRadius:0,borderWidth:3},'
            . '{label:"Batterie",data:d.battery,borderColor:theme.battery,backgroundColor:theme.batteryFill,fill:false,tension:.15,pointRadius:0,borderWidth:2.5}'
            . '];'
            . 'if(Array.isArray(d.soc)&&d.soc.length>0){datasets.push({label:"SoC",data:d.soc,borderColor:theme.soc,backgroundColor:theme.socFill,borderDash:[4,4],fill:false,tension:.15,pointRadius:0,borderWidth:2,yAxisID:"ySoc"});}'
            . 'const peakPlugin={id:"pvLoadPeakPlugin",afterEvent(chart,args){if(!enableHoverDebug||!showMarkers){return;}const ev=args.event;if(!ev){return;}const active=chart.getElementsAtEventForMode(ev.native||ev,"nearest",{intersect:false},false);let newKey=null;if(active&&active.length>0){const a=active[0];if(markers.pv&&a.datasetIndex===0&&a.index===markers.pv.index){newKey="pv";}if(markers.load&&a.datasetIndex===2&&a.index===markers.load.index){newKey="load";}}if(newKey!==hoverState.key){hoverState.key=newKey;chart.draw();}},afterDatasetsDraw(chart){if(!showMarkers){return;}const ctx=chart.ctx;ctx.save();function drawMarker(datasetIndex,marker,color,key){if(!marker||marker.index===null||marker.index===undefined){return;}const meta=chart.getDatasetMeta(datasetIndex);if(!meta||!meta.data||!meta.data[marker.index]){return;}const point=meta.data[marker.index];const x=point.x;const y=point.y;const active=(hoverState.key===key);const radius=active?8:6;ctx.beginPath();ctx.arc(x,y,radius,0,2*Math.PI);ctx.fillStyle=color;ctx.fill();ctx.lineWidth=active?3:2;ctx.strokeStyle="#ffffff";ctx.stroke();if(active){ctx.beginPath();ctx.arc(x,y,radius+5,0,2*Math.PI);ctx.strokeStyle="rgba(255,255,255,0.75)";ctx.lineWidth=2;ctx.stroke();ctx.beginPath();ctx.moveTo(x,chart.chartArea.top);ctx.lineTo(x,chart.chartArea.bottom);ctx.strokeStyle="rgba(128,128,128,0.35)";ctx.lineWidth=1;ctx.stroke();}const text=marker.label+": "+Number(marker.value).toFixed(2)+" kW";ctx.font="12px Arial";const tw=ctx.measureText(text).width;ctx.fillStyle=(theme.mode==="dark"||theme.bg==="transparent")?(active?"rgba(28,28,28,0.98)":"rgba(24,24,24,0.96)"):(active?"rgba(255,255,255,0.96)":"rgba(255,255,255,0.88)");ctx.fillRect(x+8,y-22,tw+8,16);ctx.strokeStyle=(theme.mode==="dark"||theme.bg==="transparent")?"rgba(255,255,255,0.16)":"rgba(0,0,0,0.12)";ctx.lineWidth=1;ctx.strokeRect(x+8,y-22,tw+8,16);ctx.fillStyle=color;ctx.textAlign="left";ctx.textBaseline="bottom";ctx.fillText(text,x+12,y-8);}drawMarker(0,markers.pv,theme.pv,"pv");drawMarker(2,markers.load,(theme.mode==="dark" ? "#e0e0e0" : "#000000"),"load");ctx.restore();}};'
            . 'new Chart(document.getElementById("edbSourceChart"),{type:"' . $typeEsc . '",data:{labels:d.labels,datasets:datasets},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:"index",intersect:false},plugins:{legend:{position:"top",labels:{color:theme.text}},tooltip:{backgroundColor:(theme.mode==="dark"||theme.bg==="transparent")?"rgba(24,24,24,0.96)":"rgba(255,255,255,0.96)",titleColor:(theme.mode==="dark"||theme.bg==="transparent")?"#f2f2f2":"#111111",bodyColor:(theme.mode==="dark"||theme.bg==="transparent")?"#f2f2f2":"#111111",borderColor:(theme.mode==="dark"||theme.bg==="transparent")?"rgba(255,255,255,0.16)":"rgba(0,0,0,0.12)",borderWidth:1,padding:10,displayColors:true,boxPadding:4,callbacks:{labelColor:function(context){const label=(context.dataset&&context.dataset.label)||"";const map={"PV":theme.pv,"Netz":theme.grid,"Verbrauch":(theme.mode==="dark"?"#e0e0e0":"#000000"),"Batterie":theme.battery,"SoC":theme.soc};const c=map[label]||theme.text;return {borderColor:c,backgroundColor:c};}}}},scales:{y:{ticks:{color:theme.text},grid:{color:"rgba(128,128,128,0.15)"},title:{display:true,text:"' . $unitEsc . '",color:theme.text}},ySoc:{display:(Array.isArray(d.soc)&&d.soc.length>0),position:"right",min:0,max:100,ticks:{color:theme.text},grid:{drawOnChartArea:false},title:{display:true,text:"SoC %",color:theme.text}},x:{ticks:{color:theme.text,maxTicksLimit:(d.labels.length > 30 ? 16 : 12),autoSkip:true,maxRotation:0,minRotation:0,callback:function(value){const lbl=this.getLabelForValue(value);return (typeof lbl==="string") ? lbl : value;}},grid:{color:"rgba(128,128,128,0.15)"}}}},plugins:[peakPlugin]});'
            . '})();</script>'
            . '</div>';
    }

    private function GetUsageHtml(array $data, array $totals, string $label): string
    {
        $theme = $this->GetThemeConfig();
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $height = max(220, min(460, 220 + (int) floor(count($data) * 5)));
        $labelEsc = htmlspecialchars($label);

        $balance = round((float) ($totals['pv'] ?? 0.0) - (float) ($totals['load'] ?? 0.0), 2);
        $prefix = ($balance > 0) ? '+' : '';

        $badgeBg = ($theme['mode'] === 'dark' || $theme['bg'] === 'transparent') ? 'rgba(255,255,255,0.08)' : '#ffffff';
        $badgeText = ($theme['mode'] === 'dark' || $theme['bg'] === 'transparent') ? '#ffffff' : '#333333';
        $badgeBorder = ($theme['mode'] === 'dark' || $theme['bg'] === 'transparent') ? 'rgba(255,255,255,0.16)' : '#d0d0d0';

        if ($this->ReadPropertyBoolean('ColorBalanceBadgeByState')) {
            if ($balance > 0.001) {
                $badgeBg = ($theme['mode'] === 'dark' || $theme['bg'] === 'transparent') ? 'rgba(76,175,80,0.18)' : 'rgba(76,175,80,0.14)';
                $badgeText = '#4caf50';
                $badgeBorder = 'rgba(76,175,80,0.35)';
            } elseif ($balance < -0.001) {
                $badgeBg = ($theme['mode'] === 'dark' || $theme['bg'] === 'transparent') ? 'rgba(244,67,54,0.18)' : 'rgba(244,67,54,0.12)';
                $badgeText = '#f44336';
                $badgeBorder = 'rgba(244,67,54,0.35)';
            }
        }

        $badgeHtml = '';
        if ($this->ReadPropertyBoolean('ShowBalanceBadge')) {
            $badgeHtml = '<div class="edb-badge" style="background:' . $badgeBg . ';color:' . $badgeText . ';border:1px solid ' . $badgeBorder . ';">'
                . $prefix . $this->Fmt($balance) . ' kWh'
                . '</div>';
        }

        return '<div style="font-family:Arial,sans-serif;padding:12px;color:' . $theme['text'] . ';background:' . $theme['bg'] . ';">'
            . '<style>.edb-card{background:' . $theme['bg'] . ';border:1px solid ' . $theme['border'] . ';border-radius:18px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.05)}.edb-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:4px}.edb-title{font-size:24px;font-weight:700}.edb-sub{font-size:13px;color:' . $theme['muted'] . ';margin-bottom:8px}.edb-badge{font-size:18px;font-weight:700;padding:10px 14px;border-radius:14px}.edb-wrap{position:relative;height:' . $height . 'px}</style>'
            . '<div class="edb-card"><div class="edb-head"><div class="edb-title">Stromnutzung</div>' . $badgeHtml . '</div><div class="edb-sub">' . $labelEsc . '</div><div class="edb-wrap"><canvas id="edbUsageChart"></canvas></div></div>'
            . '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>'
            . '<script>(function(){const d=' . $json . ';new Chart(document.getElementById("edbUsageChart"),{type:"bar",data:{labels:d.map(x=>x.label),datasets:['
            . '{label:"PV → Last",data:d.map(x=>x.pvToLoad),backgroundColor:"rgba(255,193,7,.55)",borderColor:"rgba(255,152,0,1)",borderWidth:1,stack:"energy"},'
            . '{label:"Netzbezug",data:d.map(x=>x.gridImport),backgroundColor:"rgba(128,203,196,.75)",borderColor:"rgba(77,182,172,1)",borderWidth:1,stack:"energy"},'
            . '{label:"Batt. Entladen",data:d.map(x=>x.batteryDischarge),backgroundColor:"rgba(100,181,246,.75)",borderColor:"rgba(66,165,245,1)",borderWidth:1,stack:"energy"},'
            . '{label:"Batt. Laden",data:d.map(x=>-x.batteryCharge),backgroundColor:"rgba(244,143,177,.72)",borderColor:"rgba(236,64,122,1)",borderWidth:1,stack:"energy"},'
            . '{label:"Netzeinspeisung",data:d.map(x=>-x.gridExport),backgroundColor:"rgba(179,157,219,.72)",borderColor:"rgba(126,87,194,1)",borderWidth:1,stack:"energy"}'
            . ']},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:"index",intersect:false},plugins:{legend:{position:"top",labels:{color:' + '$theme[\'text\']' + '}} ,scales:{y:{title:{display:true,text:"kWh"},stacked:true,ticks:{color:' + '$theme[\'text\']' + '}},x:{stacked:true,ticks:{maxRotation:0,minRotation:0,autoSkip:true,maxTicksLimit:16,color:' + json.dumps('#fff') + '}}}}});})();</script>'
            . '</div>';
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
