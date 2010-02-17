<?php
include('monadic-parser-v2.php');
//require('spyc/spyc.php');

function kapftokenrule($name, $token) {
  $mapper = new ReplayableKAPFTreeMapper();
  $cname = $mapper->atom('_rulename', $name);
  $ctoken = $mapper->atom('_token', $token);
  return $mapper->struct('_rule', array($cname, $ctoken));
}
function kapfexpressionrule($name, $type, $content) {
  $mapper = new ReplayableKAPFTreeMapper();
  $cname = $mapper->atom('_rulename', $name);
  $ccontent = array();
  foreach($content as $c):
    $ccontent[] = $mapper->atom('_reference', $c);
  endforeach;
  $cexpression = $mapper->struct($type, $ccontent);
  return $mapper->struct('_rule', array($cname, $cexpression));
}
function kapfruleset($element, $attributes = array()) {
  $mapper = new ReplayableKAPFTreeMapper();
  return $mapper->document($element, $attributes);
}

$s='1+2*2+3';

$rules = array();
$rules[] = kapftokenrule('number', '[0-9]+');
$rules[] = kapftokenrule('plus', '\\+');
$rules[] = kapftokenrule('times', '\\*');
$rules[] = kapftokenrule('openbrace', '\\(');
$rules[] = kapftokenrule('closebrace', '\\)');
$rules[] = kapfexpressionrule('simplex', 'alternative',array('number'));
$rules[] = kapfexpressionrule('product', 'sequence',array('simplex', 'times', 'productx'));
$rules[] = kapfexpressionrule('productx', 'alternative',array('product', 'simplex'));
$rules[] = kapfexpressionrule('sum', 'sequence',array('productx', 'plus', 'sumx'));
$rules[] = kapfexpressionrule('sumx', 'alternative',array('sum', 'productx'));
$rules[] = kapfexpressionrule('expression', 'alternative',array('sumx'));
$ruleset = kapfruleset($rules, array('startrulename'=>'expression'));
$parserfactory = new KAPFSimpleParserFactory();
$kapf = new KAPFController($ruleset, $parserfactory);

$source = new SimpleKAPFSource($s);
$tree = new ReplayableKAPFTreeMapper();

$kapf->compile();

$result = $kapf->process($source, $tree);
print $result->replay(new SimpleKAPFTreeSerializer());
