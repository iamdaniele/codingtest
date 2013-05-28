<?php
class Db {
	
	private static $connection;
	
	public static function getConnection() {

		global $config;
		
		if (empty($config['db']['url'])) return null;
		
		if (!isset(self::$connection)) {
			
			try {
				
				$dsn = $config['db']['url'];
				self::$connection = new PDO($dsn);
				self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			} catch (Exception $e) {
				return null;
			} // end try
			
		} // end if
		
		return self::$connection;
		
	} // end function
	
} // end class
?>