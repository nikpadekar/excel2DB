<?php
	$type_map = array(
		'varchar' => 'string',
		'char' => 'string',
		'text' => 'string',
		'int' => 'double'
	);
	class Excel_mysql {
		/**
		 * Database connection
		 */
		private $mysql_connect;

		/**
		 * File name for import /export
		 */
		private $excel_file;

		/**
		 * Class constructor
		 *
		 * connection -Database connection
		 * filename -File name for import /export
		 */
		function __construct($connection, $filename) {
			//If PHPExcel Library Is Not Connected
			if (!class_exists("\\PHPExcel")) {
				//Throw an exception
				throw new \Exception("PHPExcel library required!");
			}

			$this->mysql_connect = $connection;
			$this->excel_file    = $filename;
		}

		private
		function excel_to_mysql($worksheet, $table_name, $columns_names, $start_row_index, $table_types) {
			$columns_names = array_map('strtolower', array_map('trim',array_filter($columns_names)));
			$table_types = array_map('strtolower', array_map('trim',array_filter($table_types)));
			// Check MySQL Connection 
			if (!$this->mysql_connect->connect_error) {
				// Row for column names of MySQL table
				$columns = array();
				global $type_map;
				// Number of columns on Excel sheet
				$columns_count = \PHPExcel_Cell::columnIndexFromString($worksheet->getHighestColumn());
				
				// If an array is passed as the column name, then we check its compliance with the number of columns 
				if ($columns_names) {
					if (is_array($columns_names)) {
						
						$columns_names_fromSheet = array_map('strtolower', array_map('trim',array_filter($worksheet->toArray(Null)[0])));
						if(array_diff($columns_names,$columns_names_fromSheet) && ($columns_names != $columns_names_fromSheet)){
							throw new \Exception("Please check columns Names and sequence For ".$table_name." in template as well as in sheet");
							return false;
						}
						$columns_names_count = count(array_filter($columns_names));
						if ($columns_names_count != $columns_count) {
							throw new \Exception("No of Columns Names in template and No of Columns Names in sheet Does not match for Sheet Name ".$table_name);
							return false;
						} 
					} else {
						throw new \Exception("Unknown error for column name array creation for sheet ".$table_name);
						return false;
					}
				}

				// column types
					if (is_array($table_types)) {
						// Check the number of columns and types
						if (count($table_types) != count($columns_names)) {
							throw new \Exception("No of Columns Name array and No of Data Types array Does not match in template for Sheet Name ".$table_name);
							return false;
						}
					} else {
						throw new \Exception("invalid table type array generated. avoid using special characters in table types ".$table_name);
						return false;
					}

				$table_name = "`{$table_name}`";

				// Enumerate the columns of the Excel sheet and generate a row with names separated by commas 
				for ($column = 0; $column < $columns_count; $column++) {
					$column_name =$columns_names[$column];
					$columns[] = $column_name ? "`{$column_name}`" : null;
				}

				$query_string = "DROP TABLE IF EXISTS {$table_name}";

				// Delete the MySQL table, if it existed
				if ($this->mysql_connect->query($query_string)) {
					$columns_types = $ignore_columns = array();

					// Go around the columns and assign types
					foreach ($columns as $index => $value) {
						if ($value == null) {
							$ignore_columns[] = $index;
							unset($columns[$index]);
						} else {
							if ($table_types) {
								$columns_types[] = "{$value} {$table_types[$index]}";
							} else {
								$columns_types[] = "{$value} TEXT NOT NULL";
							}
						}
					}

					$columns_types_list = implode(", ", $columns_types);

					$query_string = "CREATE TABLE IF NOT EXISTS {$table_name} ({$columns_types_list} null) COLLATE = utf8_general_ci ENGINE = InnoDB";

					// //Create MySQL table 
					if ($this->mysql_connect->query($query_string)) {
						echo "Table : ".$table_name." Created<br>";

						// Number of rows in Excel sheet
						$rows_count = $worksheet->getHighestRow();
					
						// Looping through Excel sheet rows
						for ($row = ($start_row_index ? $start_row_index : (is_array($columns_names) ? 1 : $columns_names + 1)); $row <= $rows_count; $row++) {
							// Row with values ​​of all columns in a row of Excel sheet 
							$values = array();

							// Перебираем столбцы листа Excel
							for ($column = 0; $column < $columns_count; $column++) {
								if (in_array($column, $ignore_columns)) {
									continue;
								}

								// Excel Sheet Sheet
								$cell = $worksheet->getCellByColumnAndRow($column, $row);

								// get cell value
								$value = $cell->getValue();

								// cross check table values and data type.
								$tempColType = preg_replace('/[(][0-9]*[)]/', '', (preg_replace('/\s+/', '', $table_types[$column])));
								if(array_key_exists($tempColType, $type_map) and $type_map[$tempColType] != gettype($value)){
									throw new \Exception("DataType Error in Sheet  ".$table_name. ", Column : '".$columns_names[$column]."' and Row No ".$row."<br>Required : ".$type_map[$tempColType]." ( ".$tempColType." ) <br>Given : ".gettype($value));
									return false;
								}

								$values[] = "'{$this->mysql_connect->real_escape_string($value)}'";
							}

							// If the number of columns is not equal to the number of values, then the string did not pass the test 
							if ($columns_count - count($ignore_columns) != count($values)) {
								continue;
							}

							//Add row to MySQL table
								$columns_list = implode(", ", $columns);
								$values_list  = implode(", ", $values);

								$query_string = "INSERT INTO {$table_name} ({$columns_list}) VALUES ({$values_list})";

								if (!$this->mysql_connect->query($query_string)) {
									return false;
								}
						}
						echo "total Records Inserted ".((int)$row-(int)$start_row_index)."<br>";
						echo "table :".$table_name."execution finished.";
						return true;
					}else{
						throw new \Exception("Please verify size of Datatype and their total should match your Database criteria for ".$table_name." sheet.");
						return false;
					}
				}else{
					throw new \Exception("Error occured while dropping table for ".$table_name." sheet.");
					return false;
				}
			}

			return false;
		}

		public
		function excel_to_mysql_by_index($table_name, $columns_names, $table_types) {
			// Load the Excel file
			$PHPExcel_file = \PHPExcel_IOFactory::load($this->excel_file);
			$sheetNames = $PHPExcel_file->getSheetNames();
			if(!in_array($table_name, $sheetNames)){
				throw new \Exception("Sheet ".$table_name." not found.");
			}
			$activeSheet  = $PHPExcel_file->getSheetByName($table_name);
			return $this->excel_to_mysql($activeSheet, $table_name, $columns_names, $start_row_index=2, $table_types);
		}

		
		/**
		 * Getter file name
		 */
		public
		function getFileName() {
			return $this->excel_file;
		}
		
		/**
		 * get table array
		 */
		public
		function getTableArray() {
			return $this->tableArray;
		}
		
		/**
		 * set table array
		 */
		public
		function setTableArray($array) {
			$this->tableArray = $array;
		}
		/**
		 * File name setter
		 */
		public
		function setFileName($filename) {
			$this->excel_file = $filename;
		}

		/**
		 *Getter connection to MySQL
		 */
		public
		function getConnection() {
			return $this->mysql_connect;
		}
		/**
		 *MySQL connection setter
		 */ 
		public
		function setConnection($connection) {
			$this->mysql_connect = $connection;
		}

		/**
		 *MySQL connection setter
		 */ 
		public
		function createSQLScript($DB){
			$sqlScript = "";
			$tables = $this->getTableArray();
			$conn = $this->getConnection();
			$sqlScript .= "\n\nDROP DATABASE ".$DB.";\n\n";
			// Prepare SQLscript for creating Database structure
			$query = "SHOW CREATE SCHEMA IF NOT EXISTS $DB";
			$result = mysqli_query($conn, $query);
			$row = mysqli_fetch_row($result);
			$sqlScript .= "\n\n" . $row[1] . ";\nUSE `".$DB."`;\n\n";

			foreach ($tables as $table) {
			
				
				// Prepare SQLscript for creating table structure
				$query = "SHOW CREATE TABLE $table";
				$result = mysqli_query($conn, $query);
				$row = mysqli_fetch_row($result);
				
				$sqlScript .= "\n\n" . $row[1] . ";\n\n";
				
				
				$query = "SELECT * FROM $table";
				$result = mysqli_query($conn, $query);
				
				$columnCount = mysqli_num_fields($result);
				
				// Prepare SQLscript for dumping data for each table
				for ($i = 0; $i < $columnCount; $i ++) {
					while ($row = mysqli_fetch_row($result)) {
						$sqlScript .= "INSERT INTO $table VALUES(";
						for ($j = 0; $j < $columnCount; $j ++) {
							$row[$j] = $row[$j];
							
							if (isset($row[$j])) {
								$sqlScript .= '"' . $row[$j] . '"';
							} else {
								$sqlScript .= '""';
							}
							if ($j < ($columnCount - 1)) {
								$sqlScript .= ',';
							}
						}
						$sqlScript .= ");\n";
					}
				}
				
				$sqlScript .= "\n"; 

			}
			if(!empty($sqlScript))
			{
				
				echo "Backup FIle Generated Successfully..<br>";
				echo "Downloading..<br>";
				// Save the SQL script to a backup file
				$backup_file_name = './temp/'.$DB . '_backup_'.time().'_.sql';
				$fileHandler = fopen($backup_file_name, 'w+');
				$number_of_lines = fwrite($fileHandler, $sqlScript);
				fclose($fileHandler); 
				return $backup_file_name;
			}
			else{
				throw new \Exception("Unknown Error Occured while creating file.");
					
			}
		}
	}