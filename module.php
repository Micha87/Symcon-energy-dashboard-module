{
  "elements": [
    {
      "type": "ValidationTextBox",
      "name": "Title",
      "caption": "Titel"
    },
    {
      "type": "NumberSpinner",
      "name": "PvPowerID",
      "caption": "Variable-ID PV-Leistung (W)"
    },
    {
      "type": "NumberSpinner",
      "name": "GridPowerID",
      "caption": "Variable-ID Netzleistung (W, Bezug + / Einspeisung -)"
    },
    {
      "type": "NumberSpinner",
      "name": "LoadPowerID",
      "caption": "Variable-ID Hausverbrauch (W)"
    },
    {
      "type": "NumberSpinner",
      "name": "BatteryPowerID",
      "caption": "Variable-ID Batterie-Leistung (W, Laden - / Entladen +, optional 0)"
    },
    {
      "type": "NumberSpinner",
      "name": "ArchiveControlID",
      "caption": "Archivsteuerung-ID (0 = automatisch suchen)"
    },
    {
      "type": "NumberSpinner",
      "name": "RefreshSeconds",
      "caption": "Aktualisierung alle x Sekunden"
    },
    {
      "type": "NumberSpinner",
      "name": "BucketMinutes",
      "caption": "Balkenraster in Minuten"
    }
  ],
  "actions": [
    {
      "type": "Button",
      "caption": "Jetzt aktualisieren",
      "onClick": "EDB_UpdateVisualization($id);"
    },
    {
      "type": "Label",
      "caption": "Hinweis: Die Variable 'Visualization' wird automatisch als ~HTMLBox erzeugt."
    }
  ],
  "status": [
    {
      "code": 101,
      "icon": "inactive",
      "caption": "Wird erstellt"
    },
    {
      "code": 102,
      "icon": "active",
      "caption": "Aktiv"
    },
    {
      "code": 201,
      "icon": "error",
      "caption": "Fehler"
    }
  ]
}