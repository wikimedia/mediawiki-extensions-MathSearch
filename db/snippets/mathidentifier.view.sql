CREATE 
    ALGORITHM = UNDEFINED 
    DEFINER = `root`@`localhost` 
    SQL SECURITY DEFINER
VIEW `math_identifier` AS
    select 
        `S`.`identifier` AS `identifier`,
        `S`.`noun` AS `noun`,
        `S`.`evidence` AS `evidence`,
        `S`.`sentence` AS `sentence`,
        `S`.`sentenceHash` AS `sentenceHash`,
        `M`.`pageTitle` AS `pageTitle`,
        `M`.`pageId` AS `pageID`
    from
        (`mathsemantics` `S`
        join `mathIdMap` `M` ON ((`S`.`pageId` = `M`.`pageId`)))