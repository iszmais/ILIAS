# Content Style in der Datensammlung

Dieser Patch fügt der Datensammlung einen Content Style hinzu.
Siehe: https://docu.ilias.de/go/wiki/wpage_6989_1357

## Patch-Markierungen

Patches wurden mit `// uni-freiburg-patch: begin dc content style` und `// uni-freiburg-patch: end dc content style` markiert.

## Änderungen

Angepasst wurden im Rahmen der Funktionalität folgende Dateien:

* Modules/DataCollection/classes/DetailedView/class.ilDclDetailedViewDefinitionGUI.php
* Modules/DataCollection/classes/class.ilObjDataCollectionGUI.php

### Auswahl und Darstellug des Content Style

Innerhalb der Datensammlung kann im Reiter "Einstellungen" über den Subtab "Style" ein Content Style ausgewählt werden.
Der ausgewählte Content Style wird bei der Einzelansicht der Einträge der Datensammlung angewand.

## Spezifikation

Dieser Patch ist eine Portierung des entsprechenden Features für ILIAS 10 auf ILIAS 9 und 8.
Somit kann er ab ILIAS 10 entfernt werden.