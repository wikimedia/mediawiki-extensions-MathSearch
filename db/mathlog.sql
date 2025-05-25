--
-- Used by the math module to oganize the log files from
-- different rendering engines
--
CREATE TABLE /*_*/mathlog (
  -- Binary MD5 hash of math_inputtex, used as an identifier key.
  math_inputhash varbinary(32) NOT NULL,
  -- User input mostly tex
  math_input TEXT NOT NULL,
  -- the tex representation
  math_tex TEXT,
  -- the log input
  math_log text,
  -- the post request sent
  math_post text,
  -- (mathml|latexml) mode
  math_mode tinyint,
  -- mathml rendering
  math_mathml TEXT,
  -- svg rendering
  math_svg TEXT,
  -- time needed to answer the request in ms
  math_rederingtime int,
  -- statuscode returned by the rendering engine
  math_statuscode tinyint,
  -- timestamp
  math_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- key
  key ( math_inputhash, math_mode )
) /*$wgDBTableOptions*/;
