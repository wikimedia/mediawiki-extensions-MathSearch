UPDATE /*_*/mathlog SET math_inputhash = UNHEX(math_inputhash) WHERE math_inputhash REGEXP '^[0-9a-f]{32}$';
ALTER TABLE /*_*/mathlog MODIFY math_inputhash binary(16) NOT NULL;
UPDATE /*_*/mathindex SET mathindex_inputhash = UNHEX(mathindex_inputhash) WHERE mathindex_inputhash REGEXP '^[0-9a-f]{32}$';
ALTER TABLE /*_*/mathindex MODIFY mathindex_inputhash binary(16) NOT NULL;
UPDATE /*_*/mathperformance SET math_inputhash = UNHEX(math_inputhash) WHERE math_inputhash REGEXP '^[0-9a-f]{32}$';
ALTER TABLE /*_*/mathperformance MODIFY math_inputhash binary(16) NOT NULL;
UPDATE /*_*/mathobservation SET mathobservation_inputhash = UNHEX(mathobservation_inputhash) WHERE mathobservation_inputhash REGEXP '^[0-9a-f]{32}$';
ALTER TABLE /*_*/mathobservation MODIFY mathobservation_inputhash binary(16) NOT NULL;
