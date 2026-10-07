-- Inputs longer than $wgMathSearchContentTexMaxLength are stored as NULL
ALTER TABLE /*_*/mathlog MODIFY math_input TEXT;
