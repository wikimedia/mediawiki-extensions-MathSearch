-- As large as text.old_text, so every input of a revision fits
ALTER TABLE /*_*/mathlog MODIFY math_input MEDIUMTEXT NOT NULL;
