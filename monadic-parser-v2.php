<?php
# -*- coding: utf-8 -*-
#see http://sandersn->com/blog//index->php/2009/07/01/monadic_parsing_in_python_part_3

#todo:
# compile regex
# parser factory
# ast creation with callback by name of structure
# serialization of parser
# put in ast result class as dom object
# insert namespace
# factor out record-factory?
# think about backtracking and more than 1 result

interface InputMapper {
  function __construct($s, $i=0);
  function eof();
  function lex($pattern);
}

class SimpleInputMapper implements InputMapper {
  function __construct($s, $i=0) {
    $this->s=$s;
    $this->i=$i;
  }
  function eof() {
    return ($this->i == strlen($this->s));
  }
  function lex($pattern) {
    static $cache = array();
    $cache_index = "{$this->i}:$pattern";
    if(array_key_exists($cache_index, $cache)):
      print"Cache hit!\n";
      list($success, $result) = $cache[$cache_index];
    else:
      $success = preg_match("/^$pattern/", substr($this->s, $this->i), $matches);
      $result = $matches[0];
      $cache[$cache_index] = array($success, $result);
    endif;

    if($success){
      return new ParserResult(new SimpleInputMapper($this->s, $this->i+strlen($result)), $result, False);
    } else {
      $message = "{$this->i}# $name". substr($this->s,$this->i,10)."...\n";
      return new ParserResult($this, NULL, $message);
    }
  }
}

interface ASTMapper {
  function atom($name, $value);
  function struct($name, $value);
  function tree($value);
}

class SimpleASTMapper implements ASTMapper {
  function atom($name, $value) {
    return array('struct'=>0, 'name'=>$name, 'value'=>$value);
  }
  function struct($name, $value) {
    return array('struct'=>1, 'name'=>$name, 'value'=>$value);
  }
  function tree($value) {
    return $value;
  }
}

class SimpleASTMapper1 implements ASTMapper {
  function atom($name, $value) {
    return $value;
  }
  function struct($name, $value) {
    return "$name(".join(',',$value).")";
  }
  function tree($value) {
    return $value."\n";
  }
}

class ParserResult{
  function __construct(InputMapper $input, $ast_fragment, $fail){
    $this->input = $input;
    $this->ast_fragment= $ast_fragment;
    $this->fail = $fail;
  }
}

abstract class Parser{
  abstract function parse(SimpleInputMapper $input, ASTMapper $astmapper);
  function parseall(SimpleInputMapper $input, ASTMapper $astmapper){
    $result = $this->parse($input, $astmapper);
    if($result->fail){ 
      throw new Exception("Parser Fail: ".print_r($result));
    } elseif($result->input->eof()) {
      return $astmapper->tree($result->ast_fragment);
    } else {
      throw new Exception("Parser did not reach EOF!\n".print_r($result));
    }
  }
}

class Token extends Parser{
  function __construct($name, $pattern){
    $this->name = $name;
    $this->pattern = $pattern;
    return $this;
  }
  function parse(SimpleInputMapper $input, ASTMapper $astmapper){
    print "{$input->i}? {$this->name}\n";
    $result = $input->lex($this->pattern, $astmapper, $this->name);
    if($result->fail):
      print "{$input->i}# {$this->name}\n";
    else:
      print "{$input->i}! {$this->name}\n";
    endif;
    return new ParserResult($result->input, $astmapper->atom($name, $result->ast_fragment), $result->fail);
  }
}

class Chain extends Parser{
  function __construct($name, $parsers){
    $this->name = $name;
    assert(is_array($parsers));
    foreach($parsers as $parser){
      assert($parser instanceof Parser);
    }
    $this->parsers = $parsers;
    return $this;
  }
  function append($parsers) {
    $this->parsers = array_merge($this->parsers, $parsers);
  }
  function parse(SimpleInputMapper $input, ASTMapper $astmapper){
    print "{$input->i}? {$this->name}\n";
    $value = array();
    foreach($this->parsers as $parser){
      $result = $parser->parse($input, $astmapper);
      if($result->fail):
        print "{$input->i}# {$this->name}\n";
        return $result;
      endif;
      $input = $result->input;
      $value[] = $result->ast_fragment;
    }
    print "{$input->i}! {$this->name}\n";
    return new ParserResult($input, $astmapper->struct($this->name, $value), FALSE);
  }
}

class Any extends Parser{
  function __construct($name, $parsers){
    $this->name = $name;
    assert(is_array($parsers));
    foreach($parsers as $parser){
      assert($parser instanceof Parser);
    }
    $this->parsers = $parsers;
    return $this;
  }
  function append($parsers) {
    $this->parsers = array_merge($this->parsers, $parsers);
  }
  function parse(SimpleInputMapper $input, ASTMapper $astmapper){
    print "{$input->i}? {$this->name}\n";
    foreach($this->parsers as $parser){
      $result = $parser->parse($input, $astmapper);
      if (!$result->fail):
        print "{$input->i}! {$this->name}\n";
        return $result;
      endif;
    }
    print "{$input->i}# {$this->name}\n";
    return $result;
  }
}

class Multiple extends Parser{
  function __construct($name, $parser){
    $this->name = $name;
    assert($parser instanceof Parser);
    $this->parser = $parser;
    return $this;
  }
  function parse(SimpleInputMapper $input, ASTMapper $astmapper){
    print "{$input->i}? {$this->name}\n";
    $result = new ParserResult($input, array(), False);
    while(1) {
      $newresult = $parser->parse($result->input, $astmapper);
      if ($newresult->fail){
        break;
      }
      $astvalue[] = $result->ast_fragment;
      $result = $newresult;
    }
    print "{$input->i}".$result->fail?'#':'!'." {$this->name}\n";
    return $result;
  }
}

