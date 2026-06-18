-- Migratie: voeg 'admin', 'student' en 'instructeur' toe aan meldingen.ontvanger_type
-- Draai dit op een bestaande database (init.sql draait alleen bij eerste Docker-setup).

USE `Eend`;

ALTER TABLE `meldingen`
MODIFY `ontvanger_type` ENUM(
  'iedereen',
  'alle_studenten',
  'alle_instructeurs',
  'student',
  'instructeur',
  'admin'
) NOT NULL;
