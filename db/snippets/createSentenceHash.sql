UPDATE math_semantics 
SET 
    sentenceHash = MD5(sentence)
WHERE
    sentenceHash is null