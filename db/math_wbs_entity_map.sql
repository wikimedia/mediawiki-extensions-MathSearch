--
-- Used by MathSearch to keep track of StackExchange ids
--
CREATE TABLE /*_*/math_wbs_entity_map
(
    -- QId of the item.
    math_local_qid        int PRIMARY KEY,
    -- original id at StackExchange
    math_external_id      int NOT NULL,
    -- type of the id, e.g., post-id, user-id, ...
    math_external_id_type int NOT NULL,
    -- timestamp
    math_timestamp        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- key
    UNIQUE (math_external_id_type, math_external_id)
) /*$wgDBTableOptions*/;