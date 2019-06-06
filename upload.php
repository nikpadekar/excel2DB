<?php
require_once __DIR__ . "/mySQLConn.php";
require_once __DIR__ . "/PHPExcel/Classes/PHPExcel.php";
require_once __DIR__ . "/library/excel_mysql.php";

$mimes = array('application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/xls','text/xlsx');
if(isset($_POST["submit"])) {
	if(in_array($_FILES["fileToUpload"]["type"],$mimes)){
		echo "valid Excel Uploaded"."<br>";
		try{
			$objPHPExcel = PHPExcel_IOFactory::load($_FILES["fileToUpload"]["tmp_name"]);
			$fileName = pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_FILENAME); // returns file name
			$sheetNames = $objPHPExcel->getSheetNames();
			if(!in_array('template', $sheetNames)){
				throw new \Exception("Sheet : `template` not found.[case sensitive]");
			}
			$allDataInSheet = $objPHPExcel->getSheetByName('template');
			$columns_count = \PHPExcel_Cell::columnIndexFromString($allDataInSheet->getHighestColumn());
			if($columns_count <= 1) throw new \Exception("template is not valid ");
			$allDataInSheet = $allDataInSheet->toArray(Null);
			
			$arrayCount = count($allDataInSheet);  // Here get total count of row in that Excel sheet
			$rowIndex=2;
			$nullLineIndex=0;
			$sheetNo=1;
			//if db name in template is default or null then file name will be taken else value given in template is taken for DB creation
			$DB_Info_from_template  = ($allDataInSheet[0][1] == "" || strtolower($allDataInSheet[0][1]) == "default" || strtolower($allDataInSheet[0][1]) == Null ) ? $fileName : $allDataInSheet[0][1];
			echo "Data base Name : ".$DB_Info_from_template;
			echo "<br>";
			// Change database to "test"
			// Create database
			$DB_Drop = "DROP DATABASE ".$DB_Info_from_template."";
			$sqlDB_Create_Query = "CREATE DATABASE ".$DB_Info_from_template."";
			$DB_Created = false;
			
			if ($conn->query($sqlDB_Create_Query) === TRUE) {
				echo "Database ".$DB_Info_from_template." Created successfully ";
				echo "<br>";
				$DB_Created = true;

			} else {
				echo "Error Occured. <br> Trying to drop Database If its already Exists \n ";
				echo "<br>";
				if($conn->query($DB_Drop) === TRUE){
					echo "Database with name ".$DB_Info_from_template." Dropped";
					echo "<br>";
				}else{
					echo $conn->error;
				}
				if ($conn->query($sqlDB_Create_Query) === TRUE) {
					echo "Database ".$DB_Info_from_template." Created successfully";
					echo "<br>";
					$DB_Created = true;
				}else{
					throw new \Exception("Error creating database: " . $conn->error);
				}
			}
			if($DB_Created){
				mysqli_select_db($conn,$DB_Info_from_template);
				$excel_mysqlt = new Excel_mysql($conn, $_FILES["fileToUpload"]["tmp_name"]);
				echo "<br>";
				$tablesArr = array();
				while($arrayCount>= $rowIndex){
					$table_name= $allDataInSheet[$rowIndex-2][1];
					$columns_names=$allDataInSheet[$rowIndex-1];
					$table_types=$allDataInSheet[$rowIndex];
					$nullLineIndex++;
					if($nullLineIndex==3){
						echo "Creating Table for sheet ".$table_name."<br>";
						echo $excel_mysqlt->x2sql($table_name,$columns_names, $table_types) ? "OK\n" : "FAIL\n";
						echo "<br><br>";
						array_push($tablesArr,$table_name);
						$sheetNo++;
						$rowIndex++;
						$nullLineIndex=0;
					}
					$rowIndex++;
				}
				echo "DataBase `".$DB_Info_from_template."` Has Been Created Successfully.<br>";
				echo "Generating MySQL Backup FIle..<br>";
				$excel_mysqlt->setTableArray($tablesArr);
				
				$backup_file_name = $excel_mysqlt->createSQLScript($DB_Info_from_template);
				echo '<script type="text/javascript">'; 
				echo 'window.location= "'.$backup_file_name.'";';
				echo '</script>'; 
				
			}
		} catch(Exception $e) {
            die('<br>Error Occured :-<br> '.$e->getMessage());
		}
		
	}else{
		echo "Please Upload only Excel sheet File";
	}
}
?>