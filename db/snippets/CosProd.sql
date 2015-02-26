delimiter $$

CREATE DEFINER=`root`@`localhost` FUNCTION `CosProd`(IDA INT,IDB INT  ) RETURNS decimal(20,10)
    READS SQL DATA
    DETERMINISTIC
BEGIN
-- Calculates the CosineSimilarity of two pages
return (
	SELECT SUM(CAST(LOG( a.`pagestat_featurecount`)* LOG(b.`pagestat_featurecount`) as DECIMAL(20,10))
		/(LOG(varstat_featurecount)*LOG(varstat_featurecount)) )/(getNorm(IDA)* getNorm(IDB))
	from mathrevisionstat as a, mathrevisionstat as b,  mathvarstat as s
	WHERE (b.revstat_revid= IDA and a.revstat_revid=IDB
	and a.revstat_featureid=b.revstat_featureid
	and a.revstat_featureid=s.varstat_id)
);
END$$

