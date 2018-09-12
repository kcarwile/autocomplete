<?php

include '../vendor/autoload.php';
use KCarwile\Autocomplete\Autocomplete;
$autocomplete = Autocomplete::instance();

assert( class_exists( 'SQLite3' ) );

$autocomplete->executeCommandString( "ADD user u1 1.0 John Hancock" );
$autocomplete->executeCommandString( "ADD user u2 1.0 John Black" );
$autocomplete->executeCommandString( "ADD topic t1 0.8 John Hancock" );
$autocomplete->executeCommandString( "ADD sentence s1 0.5 What does John Hancock do all day?" );
$autocomplete->executeCommandString( "ADD sentence s2 0.5 How did John Hancock learn cursive?" );

assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "QUERY 10 John" ) ) ) == 'u2 u1 t1 s2 s1' );
assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "QUERY 10 John Ha" ) ) ) == 'u1 t1 s2 s1' );
assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "QUERY 10 John Cheever" ) ) ) == '' );
assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "QUERY 10 LEARN how" ) ) ) == 's2' );
assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "QUERY 1 lear H" ) ) ) == 's2' );
assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "QUERY 0 lea" ) ) ) == '' );
assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "WQUERY 10 0 John Ha" ) ) ) == 'u1 t1 s2 s1' );
assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "WQUERY 10 1 s1:8 John Ha" ) ) ) == 's1 u1 t1 s2' );
assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "WQUERY 2 1 topic:9.99 John Ha" ) ) ) == 't1 u1' );
$autocomplete->executeCommandString( "DEL u2" );
assert( implode( ' ', array_map( function( $r ) { return $r['id']; }, $autocomplete->executeCommandString( "QUERY 2 John" ) ) ) == 'u1 t1' );

