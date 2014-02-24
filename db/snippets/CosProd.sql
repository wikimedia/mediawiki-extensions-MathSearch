delimiter $$

CREATE DEFINER=`root`@`localhost` FUNCTION `CosProd`(IDA INT,IDB INT  ) RETURNS decimal(20,10)
    READS SQL DATA
    DETERMINISTIC
BEGIN
-- Calculates the CosineSimilarity of two pages
return (
	SELECT SUM(CAST(LOG( a.`pagestat_featurecount`)* LOG(b.`pagestat_featurecount`) as DECIMAL(20,10))
		/(LOG(varstat_featurecount)*LOG(varstat_featurecount)) )/(getNorm(IDA)* getNorm(IDB))
	from mathpagestat as a, mathpagestat as b,  mathvarstat as s
	WHERE (b.`pagestat_pageid`= IDA and a.`pagestat_pageid`=IDB 
	and a.`pagestat_featureid`=b.`pagestat_featureid` 
	and a.`pagestat_featureid`=s.varstat_id)
);
END$$

