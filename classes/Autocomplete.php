<?php

namespace KCarwile\Autocomplete;

use KCarwile\Autocomplete\Commands;
use KCarwile\Autocomplete\Models;

/**
 * Autocomplete Class
 * 
 */
class Autocomplete {
	
	/**
	 * @var	object	Autocomplete
	 */
	protected static $instance;
	
	/**
	 * @var object	Database Handle
	 */
	protected $db;
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	protected function __construct() { 
		$this->db = new \PDO('sqlite::memory:');
		$this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->db->query("CREATE TABLE IF NOT EXISTS users ( id varchar(255) primary key, score float(5,2), data varchar(2048) )");
		$this->db->query("CREATE TABLE IF NOT EXISTS topics ( id varchar(255) primary key, score float(5,2), data varchar(2048) )");
		$this->db->query("CREATE TABLE IF NOT EXISTS sentences ( id varchar(255) primary key, score float(5,2), data varchar(2048) )");
	}
	
	/**
	 * Get the db handle
	 *
	 * @return	PDO
	 */
	public function getDb()
	{
		return $this->db;
	}
	
	/**
	 * Singleton Pattern
	 *
	 * @return	Autocomplete
	 */
	public function instance()
	{
		if ( isset( static::$instance ) ) {
			return static::$instance;
		}
		
		static::$instance = new static();
		
		return static::$instance;
	}
	
	/**
	 * Execute a command string
	 * 
	 * @param	string		$command_string 			The command string to parse
	 * @return	array|NULL
	 */
	public function executeCommandString( $command_string )
	{
		$command_parts = preg_split('/\s+/', $command_string );
		$directive = array_shift( $command_parts );
		
		switch( strtolower( $directive ) ) {
			/**
			 * Add Command
			 */
			case 'add':
				$model_name = array_shift( $command_parts );
				$id = array_shift( $command_parts );
				$score = array_shift( $command_parts );
				$data = implode( ' ', $command_parts );
				
				$tablename = $this->getTableName( $model_name );
				$query = $this->db->query( "INSERT INTO {$tablename} ( id, score, data ) VALUES ( \"{$id}\", {$score}, \"{$data}\" )" );
				break;
			
			/**
			 * Delete Command
			 */
			case 'del':
				$id = array_shift( $command_parts );
				
				$this->db->query( "DELETE FROM users WHERE id = \"{$id}\"" );
				$this->db->query( "DELETE FROM topics WHERE id = \"{$id}\"" );
				$this->db->query( "DELETE FROM sentences WHERE id = \"{$id}\"" );
				break;
			
			/**
			 * Query Command
			 */
			case 'query':
				$max_results = array_shift( $command_parts );
				$search_terms = $command_parts;
				
				$results = $this->db->query("
					SELECT id, score FROM users WHERE " . implode( ' AND ', $this->getSearchClauses( "LOWER(users.data)", $search_terms )  ) . "
					UNION SELECT id, score FROM topics WHERE " . implode( ' AND ', $this->getSearchClauses( "LOWER(topics.data)", $search_terms )  ) . "
					UNION SELECT id, score FROM sentences WHERE " . implode( ' AND ', $this->getSearchClauses( "LOWER(sentences.data)", $search_terms )  ) . "
						ORDER BY score DESC, id DESC
							LIMIT {$max_results}
				")->fetchAll();
				
				return $results;
			
			/**
			 * WQuery Command
			 */
			case 'wquery':
				$max_results = array_shift( $command_parts );
				$num_boosts = array_shift( $command_parts );
				$boosts = $num_boosts > 0 ? array_map( function() use ( &$command_parts ) { return array_shift( $command_parts ); }, range( 1, $num_boosts ) ) : [];
				$search_terms = $command_parts;
				
				$user_boost = $topic_boost = $sentence_boost = 0;
				$id_boost = array();
				
				foreach( $boosts as $boost ) {
					list( $type, $amount ) = explode( ':', $boost );
					switch( $type ) {
						case 'user': $user_boost += $amount; break;
						case 'topic': $topic_boost += $amount; break;
						case 'sentence': $sentence_boost += $amount; break;
						default: @$id_boost[ $type ] += $amount; break;
					}
				}
				
				$results = $this->db->query("
					SELECT id, {$this->getScoreClause($user_boost, $id_boost)} FROM users WHERE " . implode( ' AND ', $this->getSearchClauses( "LOWER(users.data)", $search_terms )  ) . "
					UNION SELECT id, {$this->getScoreClause($topic_boost, $id_boost)} FROM topics WHERE " . implode( ' AND ', $this->getSearchClauses( "LOWER(topics.data)", $search_terms )  ) . "
					UNION SELECT id, {$this->getScoreClause($sentence_boost, $id_boost)} FROM sentences WHERE " . implode( ' AND ', $this->getSearchClauses( "LOWER(sentences.data)", $search_terms )  ) . "
						ORDER BY score DESC, id DESC
							LIMIT {$max_results}
				")->fetchAll();
				
				return $results;
				
			default:
				throw new \Exception( "Unrecognized command: {$command_string}. Try one of [ADD, DEL, QUERY, WQUERY]" );
		}
	}
	
	/**
	 * Get the table name
	 *
	 * @param	string		$model 			The model name
	 * @return	string
	 */
	public function getTableName( $model )
	{
		return strtolower( $model ) . 's';
	}
	
	/**
	 * Get an array of SQL clauses to match search terms
	 *
	 * @param	string		$source				SQL column to match
	 * @param	array		$search_terms		Terms to match
	 * @return	array
	 */
	public function getSearchClauses( $source, $search_terms ) {
		return array_map( function( $term ) use ( $source ) {
			return "({$source} LIKE \"{$term}%\" OR {$source} LIKE \"% {$term}%\")";
		}, $search_terms );
	}
	
	/**
	 * Get a SQL case clause for a score boost
	 *
	 * @param	int|float	$base_boost		The base boost
	 * @param	array		$id_boost		An array of boosts for specific ids
	 * @return	string
	 */
	public function getScoreClause( $base_boost, $id_boost )
	{
		$case_clauses = array();
		foreach( $id_boost as $id => $boost ) {
			$boost += $base_boost;
			$case_clauses[] = "WHEN '{$id}' THEN score * {$boost}";
		}
		$adjusted_boost = $base_boost == 0 ? 1 : $base_boost;
		return ! empty( $id_boost ) ? "CASE id " . implode( ' ', $case_clauses ) . " ELSE score * {$adjusted_boost} END score" : "score * {$adjusted_boost} as score";		
	}
}