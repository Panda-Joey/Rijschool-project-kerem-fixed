-- Zet tijdelijk de controle op vreemde sleutels uit om de tabel leeg te kunnen maken
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `Eend`.`studenten`;
SET FOREIGN_KEY_CHECKS = 1;