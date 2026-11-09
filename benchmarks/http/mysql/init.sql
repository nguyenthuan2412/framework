-- Schema theo TechEmpower: bảng world 10.000 dòng
CREATE TABLE world (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  randomNumber INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

SET SESSION cte_max_recursion_depth = 10000;
INSERT INTO world (randomNumber)
WITH RECURSIVE seq (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM seq WHERE n < 10000)
SELECT FLOOR(1 + RAND() * 10000) FROM seq;
