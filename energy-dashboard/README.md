[README.md](https://github.com/user-attachments/files/26358622/README.md)
# SymconEnergyDashboard

IP-Symcon Modul für ein Energie-Dashboard ähnlich dem Home-Assistant-Energy-View.

## Funktionen

- **Stromquellen** als Linienchart
  - PV
  - Netz
  - Hausverbrauch
  - Batterie optional
- **Stromnutzung** als gestapelter Balkenchart
  - PV → Last
  - Netzbezug
  - Batterieladung / -entladung
  - Netzeinspeisung
- Tagessummen in kWh
- automatische Aktualisierung per Timer
- Ausgabe in einer automatisch erzeugten `~HTMLBox`-Variable

## Voraussetzungen

- IP-Symcon mit aktivierter Archivsteuerung
- geloggte Leistungswerte in **Watt**
- Vorzeichen:
  - Netz: **Bezug positiv**, **Einspeisung negativ**
  - Batterie: **Entladen positiv**, **Laden negativ**
- Hausverbrauch als positive Leistung in Watt
- PV-Leistung als positive Leistung in Watt

## Installation

1. ZIP herunterladen und entpacken
2. Ordner `SymconEnergyDashboard` in dein Symcon-Modulverzeichnis kopieren
3. Modulverwaltung öffnen
4. Modul `EnergyDashboard` installieren
5. Instanz anlegen
6. Variablen-IDs in den Eigenschaften eintragen

## Empfohlene Zuordnung mit deinen IDs

- PV-Leistung: `53741`
- Netzleistung: `57507`
- Hausverbrauch: `54015`
- Batterie-Leistung: optional eigene ID

## Hinweise

- Das Modul nutzt `AC_GetLoggedValues`.
- Für sehr viele Logeinträge kann die Darstellung etwas schwerer werden.
- Wenn du willst, kann man als nächsten Schritt noch ergänzen:
  - Tagesnavigation vor/zurück
  - konfigurierbare Farben
  - echte Home-Assistant-ähnliche Kartenoptik
  - Sankey zusätzlich
  - Autarkie- und Eigenverbrauchsquote


## Wichtige Änderung

- Das Modul begrenzt die Datenmenge für den Chart **Stromquellen** über die Eigenschaft **MaxSourcePoints** (Standard: 240), damit die HTMLBox unter dem Symcon-Limit von 1024 kB bleibt.
