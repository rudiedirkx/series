<?php

namespace rdx\series;

abstract class UserModel extends Model {

	static function insert( array $data ) {
		isset($data['user_id']) || $data['user_id'] = USER_ID;

		return parent::insert($data);
	}



	static function count( $conditions, array $params = array() ) : int {
		self::_extendUserConditions($conditions, $params);
		return parent::count($conditions, $params);
	}

	static function first( $conditions, array $params = [] ) {
		self::_extendUserConditions($conditions, $params);
		return parent::first($conditions, $params);
	}

	static function all( $conditions, array $params = [] ) {
		self::_extendUserConditions($conditions, $params);
		return parent::all($conditions, $params);
	}



	/**
	 * @param string|array<int|string, mixed> $conditions
	 * @param list<mixed> $params
	 */
	static protected function _extendUserConditions( string|array &$conditions, array &$params ) : void {
		if ( is_array($conditions) ) {
			$conditions['user_id'] = USER_ID;
		}
		else {
			$conditions = "user_id = ? AND $conditions";
			array_unshift($params, USER_ID);
		}
	}

}
