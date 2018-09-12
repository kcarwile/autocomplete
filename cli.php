<?php

include 'vendor/autoload.php';
use KCarwile\Autocomplete\Autocomplete;
$autocomplete = Autocomplete::instance();

$f = fopen( 'php://stdin', 'r' );
$command_count = trim( fgets( $f ) );

if ( ! is_numeric( $command_count ) ) {
	die( 'Input should start with the number of commands to process.' );
}

$commands = array();

for( $i=0; $i<$command_count; $i++ ) {
	$commands[] = trim( fgets( $f ) );
}

foreach( $commands as $command_string ) {
	$results = $autocomplete->executeCommandString( $command_string );
	if ( is_array( $results ) ) {
		echo implode( ' ', array_column( $results, 'id' ) ) . "\n";
	}
}