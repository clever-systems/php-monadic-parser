<?php
include('monadic-parser.php');

$s='1+2*2+3';


$number=new Token('/[0-9]+/');
$plus=new Token('/\+/');
$times=new Token('/\*/');
$openbrace=new Token('/\(/');
$closebrace=new Token('/\)/');

$simple_array = array($number);
$simple = new Any($simple_array,'simple');

$sum_array=array();
$sum=new Chain($sum_array, 'sum');
$sum_array+=array($simple,$plus, $sum);

$product_array=array();
$product=new Chain($product_array, 'product');
$product_array+=array($sum,$times, $product);

$expression=$product;
$simple_array[]= array($openbrace, $expression, $closebrace);

print_r($expression->parsestring($s));
