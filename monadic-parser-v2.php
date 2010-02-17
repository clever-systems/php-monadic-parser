<?php
# -*- coding: utf-8 -*-

#implementation
# stdclass from array? use for kapf-tree
# array class? use for rulestore
# auto register parser modules
# auto register parser engines
# does it make sense to factor out cache class?
# factor out record-factory?
# think about backtracking and more than 1 result
# rename treemapper like dom?
#usecases
# macros in grammars
# parser chains
#TODO
# write engine
# clone()
# findproblems()
# compile()
# serializers
# dom implementation
#TODO now
# refactor kapfparsingexpression so we throw in messages... well we need this only to bootstrap so ok
# refactor so to use standard nomenclatura for tree (dom) and grammar (PEG)
# maybe factor out parser engine in separate class, or rename methods
# rethink replay by method
# parser state variables
# parsers accept kapf-partial-result (rename again?)
# kapf-partial-result + state

interface KAPFTreeMapper {
  function atom($name, $content, $attributes = array());
  function struct($name, $content, $attributes = array());
  function document($element, $attributes = array());
}

class SimpleKAPFTreeSerializer implements KAPFTreeMapper {
  function atom($name, $content, $attributes = array()) {
    $results = array();
    foreach($attributes as $k=>$v):
      $results[] = "$k=$v";
    endforeach;
    $allresults = join(';', $results);
    return $content."[$allresults]";
  }
  function struct($name, $content, $attributes = array()) {
    $results = array();
    foreach($content as $k=>$v):
      if(! $v instanceof KAPFTreeReplayable) throw new Exception("Cannot replay content of $name($k): \n".print_r($content,1));
      $results[$k] = $v->replay($this);
    endforeach;
    $allresults = join(';', $results);
    return "$name($allresults)";
  }
  function document($content, $attributes = array()) {
    return $content->replay($this)."\n";
    $results = array();
    foreach($content as $k=>$v):
      if(! $v instanceof KAPFTreeReplayable) throw new Exception("Cannot replay content of $name($k): \n".print_r($content,1));
      $results[$k] = $v->replay($this);
    endforeach;
    $allresults = join(';', $results);
    return "$name($allresults)";
  }
}

interface KAPFTreeReplayable {
  function replay(KAPFTreeMapper $treemapper);
}
class ReplayableKAPFTreeElement implements KAPFTreeReplayable {
  function __construct($replaymethod, $arguments) {
    $this->replaymethod = $replaymethod;
    $this->arguments = $arguments;
  }
  function replay(KAPFTreeMapper $treemapper) {
    $arguments = $this->arguments;
    return call_user_func_array(array($treemapper, $this->replaymethod), $this->arguments);
  }
  function replaybyname(KAPFTreeMapper $treemapper) {
    $arguments = $this->arguments;
    list($this->arguments['name'], $this->replaymentod) = array($this->replaymentod, $this->arguments['name']);
    return call_user_func_array(array($treemapper, $this->replaymethod), $this->arguments);
  }
  function __toString() {
    if(0 and class_exists(SPYC)):
      return SPYC::YAMLDump($this);
    else:
      return print_r($this,1);
    endif;
  }
}
class ReplayableKAPFTreeMapper implements KAPFTreeMapper {
  function atom($name, $content, $attributes = array()) {
    return new ReplayableKAPFTreeElement('atom', array('name'=>$name, 'content'=>$content, 'attributes'=>$attributes));
  }
  function struct($name, $content, $attributes = array()) {
    return new ReplayableKAPFTreeElement('struct', array('name'=>$name, 'content'=>$content, 'attributes'=>$attributes));
  }
  function document($content, $attributes = array()) {
    return new ReplayableKAPFTreeElement('document', array('content'=>$content, 'attributes'=>$attributes));
  }
}

// class SimpleKAPFRuleStore = array(string=>SimpleKAPFTree);

interface KAPFSource {
  function __construct($s, $i=0);
  function eof();
  function lex($pattern);
}

class SimpleKAPFSource implements KAPFSource {
  function __construct($s, $i=0) {
    $this->s=$s;
    $this->i=$i;
  }
  function eof() {
    return ($this->i == strlen($this->s));
  }
  function lex($pattern) {
    $success = preg_match("/^$pattern/", substr($this->s, $this->i), $matches);
    $result = $matches[0];
    if($success){
      return new KAPFPartialResult(new SimpleKAPFSource($this->s, $this->i+strlen($result)), $result, False);
    } else {
      $message = "{$this->i}# $name". substr($this->s,$this->i,10)."...\n";
      return new KAPFPartialResult($this, NULL, $message);
    }
  }
}

class KAPFPartialResult{
  function __construct(KAPFSource $source, $tree, $fail){
    $this->source = $source;
    $this->tree= $tree;
    $this->fail = $fail;
  }
}

class KAPFController {
  function __construct($ruleset, $parserfactory) {
    $this->ruleset = $ruleset;
    $this->parserfactory = $parserfactory;
  }
  function compile() {
    $this->parser = $this->ruleset->replay($this->parserfactory);
  }
  function process(KAPFSource $source, KAPFTreeMapper $treemapper) {
    $state = array();
    return $this->parser->parse($source, $treemapper, $state);
  }
}

interface KAPFParserFactory extends KAPFTreeMapper {
}

class KAPFSimpleParserFactory implements KAPFParserFactory {
  function atom($name, $content, $attributes = array()) {
    switch($name):
    case('_token'):
      return new KAPFSimpleParserToken($content, $attributes);
    case('_rulename'):
      return $content;
    case('_reference'): 
      $result = new KAPFSimpleParserReference($content, $attributes, $this->ruleset);
      $this->references[] = $result;
      return $result;
    default:
      throw new Exception("Compiler does not know Atom: $name");
    endswitch;
  }
  function struct($name, $content, $attributes = array()) {
    $compiledcontent = array();
    foreach($content as $item):
      if(!($item instanceof KAPFTreeReplayable)) throw new Exception("Content of struct must be replayable: ".print_r($content,1));
      $compiled = $item->replay($this);
      $compiledcontent[] = $compiled;
    endforeach;
    switch($name):
    case('_rule'):
      if(count($content)!=2) throw new Exception("Rule must have 2 parts.");
      $rulename = $compiledcontent[0];
      $parser = $compiledcontent[1];
      if(!is_string($rulename)) throw new Exception("Rulename must be string.");
      if(!$parser instanceof KAPFParser) throw new Exception("Expression must be parser: ".print_r($content,1));
      $this->ruleset[$rulename] = $parser;
      return $parser;
    case('sequence'):
      return new KAPFSimpleParserSequence($compiledcontent, $attributes);
    case('alternative'):
      return new KAPFSimpleParserAlternative($compiledcontent, $attributes);
    default:
      throw new Exception("Compiler does not know Struct: $name");
    endswitch;
  }
  function document($content, $attributes = array()) {
    $this->references = array();
    $this->ruleset = array();
    foreach($content as $item):
      $compiled = $item->replay($this);
      if(!$compiled instanceof KAPFParser) throw new Exception("Expression in rule must be parser: ".print_r($content,1));
    endforeach;
    $attributes['_start'] = $this->ruleset[$attributes['startrulename']];
    return new KAPFSimpleParserDocument($this->ruleset, $attributes);
  }
}

interface KAPFParser {
  function parse(KAPFSource $source, KAPFTreeMapper $treemapper, $state);
  function parse_nocache(KAPFSource $source, KAPFTreeMapper $treemapper, $state);
}

abstract class KAPFSimpleParserBase implements KAPFParser {
  function parse(KAPFSource $source, KAPFTreeMapper $treemapper, $state) {
    static $cache = array();
    $arguments = array($this, $source, $treemapper, $state);
    $cache_index = serialize($arguments);
    if(array_key_exists($cache_index, $cache)):
      print"Cache hit!\n";
      $result = unserialize($cache[$cache_index]);
    else:
      $result = $this->parse_nocache($source, $treemapper, $state);
      $cache[$cache_index] = serialize($result);
    endif;
    return $result;
  }
  function _is_parserlist($parsers) {
    assert(is_array($parsers));
    foreach($parsers as $parser):
      assert($parser instanceof KAPFParser);
    endforeach;
  }
}


class KAPFSimpleParserReference extends KAPFSimpleParserBase {
  function __construct($content, $attributes, &$dict) {
    $this->name = 'reference';
    $this->content = $content;
    $this->dict = &$dict;
    return $this;
  }
  function parse_nocache(KAPFSource $source, KAPFTreeMapper $treemapper, $state) {
    print "REF: {$this->content}\n";
    $result = $this->dict[$this->content]->parse($source, $treemapper, $state);
    $result->tree = $treemapper->struct($this->content, array($result->tree));
    return $result;
  }
}

class KAPFSimpleParserDocument extends KAPFSimpleParserBase {
  function __construct($content, $attributes) {
    $this->name = 'document';
    $this->content = $content;
    $this->start = $attributes['_start'];
    return $this;
  }
  function parse_nocache(KAPFSource $source, KAPFTreeMapper $treemapper, $state) {
    $result = $this->start->parse($source, $treemapper, $state);
    if($result->fail){ 
      throw new Exception("Parser Fail: ".print_r($result));
    } elseif($result->source->eof()) {
      return $treemapper->document($result->tree);
    } else {
      throw new Exception("Parser did not reach EOF!\n".print_r($result));
    }
  }
}

class KAPFSimpleParserToken extends KAPFSimpleParserBase {
  function __construct($pattern, $attributes){
    $this->name = 'token';
    $this->pattern = $pattern;
    return $this;
  }
  function parse_nocache(KAPFSource $source, KAPFTreeMapper $treemapper, $state){
    print "{$source->i}? {$this->name} {$this->pattern}\n";
    $result = $source->lex($this->pattern, $treemapper, $this->name);
    if($result->fail):
      print "{$source->i}# {$this->name} {$this->pattern}\n";
    else:
      print "{$source->i}! {$this->name} {$this->pattern}\n";
    endif;
    return new KAPFPartialResult($result->source, $treemapper->atom($name, $result->tree), $result->fail);
  }
}

class KAPFSimpleParserSequence extends KAPFSimpleParserBase {
  function __construct($parsers, $attributes) {
    $this->name = 'sequence';
    $this->_is_parserlist($parsers);
    $this->parsers = $parsers;
    return $this;
  }
  function append($parsers) {
    $this->_is_parserlist($parsers);
    $this->parsers = array_merge($this->parsers, $parsers);
  }
  function parse_nocache(KAPFSource $source, KAPFTreeMapper $treemapper, $state){
    print "{$source->i}? {$this->name}\n";
    $value = array();
    foreach($this->parsers as $parser){
      $result = $parser->parse($source, $treemapper, $state);
      if($result->fail):
        print "{$source->i}# {$this->name}\n";
        return $result;
      endif;
      $source = $result->source;
      $value[] = $result->tree;
    }
    print "{$source->i}! {$this->name}\n";
    return new KAPFPartialResult($source, $treemapper->struct($this->name, $value), FALSE);
  }
}

class KAPFSimpleParserAlternative extends KAPFSimpleParserBase {
  function __construct($parsers, $attributes){
    $this->name = 'alternative';
    $this->_is_parserlist($parsers);
    $this->parsers = $parsers;
    return $this;
  }
  function append($parsers) {
    $this->_is_parserlist($parsers);
    $this->parsers = array_merge($this->parsers, $parsers);
  }
  function parse_nocache(KAPFSource $source, KAPFTreeMapper $treemapper, $state){
    print "{$source->i}? {$this->name}\n";
    foreach($this->parsers as $i=>$parser){
      $result = $parser->parse($source, $treemapper, $state);
      if (!$result->fail):
        print "{$source->i}! {$this->name}\n";
        $source = $result->source;
        $value = array($result->tree);
        return new KAPFPartialResult($source, $treemapper->struct($i, $value), FALSE);
        //return $result;
      endif;
    }
    print "{$source->i}# {$this->name}\n";
    return $result;
  }
}

class KAPFSimpleParserRepeating extends KAPFSimpleParserBase {
  function __construct($parser, $attributes){
    $this->name = 'repeating';
    assert($parser instanceof KAPFParser);
    $this->parser = $parser;
    return $this;
  }
  function parse_nocache(KAPFSource $source, KAPFTreeMapper $treemapper, $state){
    print "{$source->i}? {$this->name}\n";
    $result = new KAPFPartialResult($source, array(), False);
    while(1) {
      $newresult = $parser->parse($result->source, $treemapper, $state);
      if ($newresult->fail){
        break;
      }
      $astvalue[] = $result->tree;
      $result = $newresult;
    }
    print "{$source->i}".$result->fail?'#':'!'." {$this->name}\n";
    return $result;
  }
}
