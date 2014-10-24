csvadapter
==========

Csvadapter ist eine Erweiterung für die OntoWiki Knowledge Base Verwaltungssystem für die Konvertierung von Tabellen aus CSV-Datein in die Triples, die in der Knowledge Base abgespeichert werden und weiterverwaltet bzw. editiert werden können.

Die Anwendung wurde als eine Lösung für das ERM Management-System für die Verwaltung von elektronischen Ressourcen in einer Bibliothek entwickelt.

Zur Verwendung soll der Csvadapter in den Extensions der OntoWiki gespeichert werden.

Die Application bearbeitet die CSV-Datei, validiert die Bücher-ISBNs falls notwendig, erstellt das Mapping-Schema. Dazu nutzt die die externe Anwendung Tarql zur Konvertierung von tabellarischen Inhalten in die Tripeln in Ttl-Format.    
