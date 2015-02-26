delimiter $$

CREATE DEFINER=`root`@`localhost` FUNCTION `getNorm`(pid INT) RETURNS decimal(20,10)
    READS SQL DATA
    DETERMINISTIC
BEGIN
DECLARE output DECIMAL(20,10);
SELECT SUM(POW(LOG(CAST(`pagestat_featurecount`as decimal(20,10)))/LOG(varstat_featurecount),2)) as norm
INTO output
FROM mathrevisionstat
JOIN mathvarstat on revstat_featureid = varstat_id
WHERE revstat_revid =pid order by norm desc;
return POW(output,1/2);
END$$

