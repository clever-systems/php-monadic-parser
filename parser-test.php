<?php
include('monadic-parser.php');

$s='1+2*2+3';


$number=new Token('number', '[0-9]+');
$plus=new Token('+', '\\+');
$times=new Token('*', '\\*');
$openbrace=new Token('(', '\\(');
$closebrace=new Token(')', '\\)');

$simplex = new Any('simple', array($number));

$product=new Chain('product', array($simplex, $times));
$productx = new Any('productx', array($product, $simplex));
$product->append(array($productx));

$sum = new Chain('sum', array($productx, $plus));
$sumx = new Any('sumx', array($sum, $productx));
$sum->append(array($sumx));

//$braced = new Chain('braced', array($openbrace, $productx, $closebrace));
//$simplex->append(array($braced));

$expression = $sumx;

print_r($expression->parseall(new SimpleInputMapper($s), new SimpleASTMapper1()));
