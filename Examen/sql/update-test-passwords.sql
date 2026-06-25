USE `Eend`;
UPDATE `studenten` SET `wachtwoord` = '$2y$10$73PovOrwuoysRFRxkGX/C.R2FzkAb6AEEuWcBNXM3zZeeYmQIyRbC' WHERE `studentID` = 1;
UPDATE `instructeurs` SET `wachtwoord` = '$2y$10$73PovOrwuoysRFRxkGX/C.R2FzkAb6AEEuWcBNXM3zZeeYmQIyRbC' WHERE `instructeurID` IN (1, 2);
