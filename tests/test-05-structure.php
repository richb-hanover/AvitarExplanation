<?php
$text = "1. \"VIEW POT GOOD TOPO\"\nGood view potential.\nGood topography.\n\n"
	. "2. “OB PONDSIDE”\nAn outbuilding.\n⚠ Uncertain: OB is not standard.\n\n"
	. "3. \"NOT \"HSE SITE\"=REC LOT\"\nValued as a recreational lot.\n\n"
	. "Summary: A pondside lot.\nWorth checking.\n\n"
	. "(The explanation was cut off.)";
$s = avitar_explainer_structure_explanation( $text );
check( 'finds each numbered item', 3 === count( $s['items'] ) );
check( 'segment loses its wrapping straight quotes', 'VIEW POT GOOD TOPO' === $s['items'][0]['segment'] );
check( 'segment loses its wrapping curly quotes', 'OB PONDSIDE' === $s['items'][1]['segment'] );
check( 'quotes inside a segment are kept', 'NOT "HSE SITE"=REC LOT' === $s['items'][2]['segment'] );
check( 'meaning lines are joined into one paragraph', 'Good view potential. Good topography.' === $s['items'][0]['meaning'] );
check( 'uncertain lines are separated from the meaning', 'An outbuilding.' === $s['items'][1]['meaning'] && array( 'OB is not standard.' ) === $s['items'][1]['uncertain'] );
check( 'items without uncertainty have an empty list', array() === $s['items'][0]['uncertain'] );
check( 'summary is extracted and joined', 'A pondside lot. Worth checking.' === $s['summary'] );
check( 'trailing notes after the summary are kept separately', array( '(The explanation was cut off.)' ) === $s['extra'] );

$s = avitar_explainer_structure_explanation( "1. \"A\"\nfirst\n2. \"B\"\nsecond" );
check( 'items without blank lines between them are split', 2 === count( $s['items'] ) && 'second' === $s['items'][1]['meaning'] );

$s = avitar_explainer_structure_explanation( "1. \"A\"\nfirst\n⚠️ Uncertain: emoji variant" );
check( 'uncertain marker with emoji variation selector is recognized', array( 'emoji variant' ) === $s['items'][0]['uncertain'] );

$s = avitar_explainer_structure_explanation( "1. \"LLA 1.16 AC TO 401-39\"\nMoved 1.16 acres.\n2. \"CU5;2/2/1\"\nCurrent Use codes." );
check( 'numbers inside meanings do not start new items', 2 === count( $s['items'] ) && 'Moved 1.16 acres.' === $s['items'][0]['meaning'] );

$s = avitar_explainer_structure_explanation( "I can only explain tax-card notes." );
check( 'text without numbered items goes to extra', array() === $s['items'] && array( 'I can only explain tax-card notes.' ) === $s['extra'] );

$s = avitar_explainer_structure_explanation( "1. \"A\"\r\nfirst\r\n\r\nSummary: done" );
check( 'Windows line endings are handled', 'first' === $s['items'][0]['meaning'] && 'done' === $s['summary'] );
