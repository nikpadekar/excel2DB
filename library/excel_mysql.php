<?php
	$type_map = array(
		'varchar' => 'string',
		'char' => 'string',
		'text' => 'string',
		'int' => 'double'
	);
	class Excel_mysql {
		/**
		 * @var mysqli -Database connection
		 */
		private $mysql_connect;

		/**
		 * @var string -File name for import /export
		 */
		private $excel_file;

		/**
		 * Class constructor
		 *
		 * @param mysqli $ connection -Database connection
		 * @param string $ filename -File name for import /export
		 *
		 * @throws Exception -PHPExcel library not found
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

				// If column types are specified
				if ($table_types) {
					if (is_array($table_types)) {
						// Check the number of columns and types
						if (count($table_types) != count($columns_names)) {
							throw new \Exception("No of Columns Name array and No of Data Types array Does not match in template for Sheet Name ".$table_name);
							return false;
						}
					} else {
						return false;
					}
				}

				$table_name = "`{$table_name}`";

				// Перебираем столбцы листа Excel и генерируем строку с именами через запятую
				for ($column = 0; $column < $columns_count; $column++) {
					$column_name = (is_array($columns_names) ? $columns_names[$column] : ($columns_names == 0 ? "column{$column}" : $worksheet->getCellByColumnAndRow($column, $columns_names)->getValue()));
					$columns[] = $column_name ? "`{$column_name}`" : null;
				}

				$query_string = "DROP TABLE IF EXISTS {$table_name}";

				// Удаляем таблицу MySQL, если она существовала (если не указан столбец с уникальным значением для обновления)
				if ($this->mysql_connect->query($query_string)) {
					$columns_types = $ignore_columns = array();

					// Обходим столбцы и присваиваем типы
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

					$columns_keys = null;
					

					$columns_types_list = implode(", ", $columns_types);

					$query_string = "CREATE TABLE IF NOT EXISTS {$table_name} ({$columns_types_list} null) COLLATE = utf8_general_ci ENGINE = InnoDB";

					// @codeCoverageIgnoreStart
					if (defined("EXCEL_MYSQL_DEBUG")) {
						if (EXCEL_MYSQL_DEBUG) {
							var_dump($query_string);
						}
					}
					// @codeCoverageIgnoreEnd

					// Создаем таблицу MySQL
					if ($this->mysql_connect->query($query_string)) {
						// Коллекция значений уникального столбца для удаления несуществующих строк в файле импорта (используется при обновлении)
						$id_list_in_import = array();

						// Количество строк на листе Excel
						$rows_count = $worksheet->getHighestRow();

						// Получаем массив всех объединенных ячеек
						$all_merged_cells = $worksheet->getMergeCells();

						// Перебираем строки листа Excel
						for ($row = ($start_row_index ? $start_row_index : (is_array($columns_names) ? 1 : $columns_names + 1)); $row <= $rows_count; $row++) {
							// Строка со значениями всех столбцов в строке листа Excel
							$values = array();

							// Перебираем столбцы листа Excel
							for ($column = 0; $column < $columns_count; $column++) {
								if (in_array($column, $ignore_columns)) {
									continue;
								}

								// Строка со значением объединенных ячеек листа Excel
								$merged_value = null;

								// Ячейка листа Excel
								$cell = $worksheet->getCellByColumnAndRow($column, $row);

								// Перебираем массив объединенных ячеек листа Excel
								foreach ($all_merged_cells as $merged_cells) {
									// @codeCoverageIgnoreStart
									// Если текущая ячейка - объединенная,
									if ($cell->isInRange($merged_cells)) {
										// то вычисляем значение первой объединенной ячейки, и используем её в качестве значения текущей ячейки
										$merged_value = explode(":", $merged_cells);

										$merged_value = $worksheet->getCell($merged_value[0])->getValue();

										break;
									}
									// @codeCoverageIgnoreEnd
								}

								// Проверяем, что ячейка не объединенная: если нет, то берем ее значение, иначе значение первой объединенной ячейки
								$value = strlen($merged_value) == 0 ? $cell->getValue() : $merged_value;

								// cross check table values and data type.
								$tempColType = preg_replace('/[(][0-9]*[)]/', '', (preg_replace('/\s+/', '', $table_types[$column])));
								if(array_key_exists($tempColType, $type_map) and $type_map[$tempColType] != gettype($value)){
									throw new \Exception("DataType Error in Sheet  ".$table_name. ", Column : '".$columns_names[$column]."' and Row No ".$row."<br>Required : ".$type_map[$tempColType]." ( ".$tempColType." ) <br>Given : ".gettype($value));
									return false;
								}

								$values[] = "'{$this->mysql_connect->real_escape_string($value)}'";
							}

							// Если количество столбцов не равно количеству значений, значит строка не прошла проверку
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

						return true;
					}else{
						throw new \Exception("Please verify size of Datatype and their total should match your Database criteria for ".$table_name." sheet.");
						return false;
					}
				}
			}

			return false;
			// @codeCoverageIgnoreEnd
		}

		/**
		 * Функция импорта листа Excel по индексу
		 *
		 * @param string     $table_name               - Имя таблицы MySQL
		 * @param int        $index                    - Индекс листа Excel
		 * @param int|array  $columns_names            - Строка или массив с именами столбцов таблицы MySQL (0 - имена типа column + n). Если указано больше столбцов, чем на листе Excel, будут использованы значения по умолчанию указанных типов столбцов. Если указано ложное значение (null, false, "", 0, -1...) столбец игнорируется
		 * @param bool|int   $start_row_index          - Номер строки, с которой начинается обработка данных (например, если 1 строка шапка таблицы). Нумерация начинается с 1, как в Excel
		 * @param bool|array $table_types              - Типы столбцов таблицы (используется при создании таблицы), в SQL формате - "INT(11)"
		 *
		 * @return bool - Флаг, удалось ли выполнить функцию в полном объеме
		 */

		public
		function excel_to_mysql_by_index($table_name, $index = 0, $columns_names = 0, $start_row_index = false, $table_types = false) {
			// Загружаем файл Excel
			$PHPExcel_file = \PHPExcel_IOFactory::load($this->excel_file);

			// Выбираем лист Excel
			$PHPExcel_file->setActiveSheetIndex($index);

			return $this->excel_to_mysql($PHPExcel_file->getActiveSheet(), $table_name, $columns_names, $start_row_index, $table_types);
		}

		
		/**
		 * Геттер имени файла
		 *
		 * @return string - Имя файла
		 */
		public
		function getFileName() {
			return $this->excel_file;
		}

		/**
		 * Сеттер имени файла
		 *
		 * @param string $filename - Новое имя файла
		 */
		public
		function setFileName($filename) {
			$this->excel_file = $filename;
		}

		/**
		 * Геттер подключения к MySQL
		 *
		 * @return mysqli - Подключение MySQL
		 */
		public
		function getConnection() {
			return $this->mysql_connect;
		}

		/**
		 * Сеттер подключения к MySQL
		 *
		 * @param mysqli $connection - Новое подключение MySQL
		 */
		public
		function setConnection($connection) {
			$this->mysql_connect = $connection;
		}
	}