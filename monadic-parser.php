<?php
# -*- coding: utf-8 -*-
#see http://sandersn->com/blog//index->php/2009/07/01/monadic_parsing_in_python_part_3

#todo:
# add exception property to parseresult
# put in ast result class as dom object
# make stateful parsebuffer object via clone
# remove $lexer
# insert namespace
# use list() = instead of parseresult

function ast_result($type, $value){
  return array('type'=>$type, 'vaule'=>$value);
}

class Ast{
  function add_token($type, $value){
  }
  function add_structure($type, $value, $content){
  }
  function dump() {
  }
}
class ParseBuffer{
  function __construct($s){
    $this->s=$s;
    $this->i=0;
  }
  function eof(){
    return ($this->i == strlen($this->s));
  }
  function match($pattern){
    if(preg_match($pattern, $this->s, $matches, 0, $this->i)){
      $this->i += strlen($matches[0]);
      return $matches[0];
    } else {
      return new Exception("ParseBuffer could not match at {$this->i}.");
    }
  }
}

class ParseResult{
  function __construct(ParseBuffer $buffer, $answer){
    $this->buffer = $buffer;
    $this->answer = $answer;
  }
}

abstract class Parser{
  function __construct(Lexer $lexer){
    $this->lexer = $lexer;
  }
  abstract function parse(ParseBuffer $buffer);
  function parsestring($s){
    $result = $this->parse(new ParseBuffer($s));
    if($result->answer instanceof Exception){ 
      throw $result->answer;
    } elseif($result->buffer->eof()) {
      return $result->answer;
    } else {
      throw new Exception("Parser did not reach EOF, only {$result->buffer->i}");
    }
  }
}


class Token extends Parser{
  function __construct($pattern, $type = 'Token'){
    $this->type = $type;
    $this->pattern = $pattern;
  }
  function parse(ParseBuffer $buffer){
    $token = $buffer->match($this->pattern);
    if($token instanceof Exception){
      return new ParseResult($buffer, $token);
    } else {
      return new ParseResult($buffer, ast_result($this->type, $token));
    }
  }
}

class Chain extends Parser{
  function __construct(&$parsers, $type = 'Chain'){
    $this->type = $type;
    assert(is_array($parsers));
    foreach($parsers as $parser){
      assert($parser instanceof Parser);
    }
    $this->parsers = &$parsers;
  }
  function parse(ParseBuffer $buffer){
    $value = array();
    foreach($this->parsers as $parser){
      $result = $parser->parse($buffer);
      if($result->answer instanceof Exception){
        break;
      }
      $buffer = $result->buffer;
      $value[] = $result->answer;
    }
    return new ParseResult($buffer, ast_result($this->type,$value));
  }
}

class Lift extends Parser{
  function __construct($parser1, $f){
    $this->parser1 = $parser1;
    $this->f = $f;
  }
  function parse(ParseBuffer $buffer){
    $result = $this->parser1->parse($buffer);
    if ($result->answer instanceof Exception){
    return new ParseResult($buffer, $result->answer);
    } else {
      return $this->f(a).parse($buffer);
    }
  }
}
class Rtrn extends Parser{
  function __construct($x){
    $this->x = $x;
  }
  function parse(ParseBuffer $buffer){
    return new ParseResult($buffer, $this->x);
  }
}
class Any extends Parser{
  function __construct(&$parsers, $type = 'Any'){
    $this->type = $type;
    assert(is_array($parsers));
    foreach($parsers as $parser){
      assert($parser instanceof Parser);
    }
    $this->parsers = &$parsers;
  }
  function parse(ParseBuffer $buffer){
    foreach($this->parsers as $parser){
      $result = $parser->parse($buffer);
      if (!($result->answer instanceof Exception)){
        break;
      }
    }
    return new ParseResult($result->buffer, ast_result($this->type,$value));
  }
}
