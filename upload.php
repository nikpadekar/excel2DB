<?php
require_once __DIR__ . "/mySQLConn.php";
require_once __DIR__ . "/PHPExcel/Classes/PHPExcel.php";
require_once __DIR__ . "/library/excel_mysql.php";
$target_dir = "uploads/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
// Check if image file is a actual image or fake image
$mimes = array('application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/xls','text/xlsx');
if(isset($_POST["submit"])) {
	if(in_array($_FILES["fileToUpload"]["type"],$mimes)){
		echo "valid Excel Uploaded"."<br>";
		try{
			$objPHPExcel = PHPExcel_IOFactory::load($_FILES["fileToUpload"]["tmp_name"]);
			$fileName = pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_FILENAME); // returns file name
			$allDataInSheet = $objPHPExcel->getSheetByName('templete')->toArray(null);
			$arrayCount = count($allDataInSheet);  // Here get total count of row in that Excel sheet
			$rowIndex=2;
			$nullLineIndex=0;
			//if db name in template is default or null then file name will be taken else value given in template is taken for DB creation
			$DB_Info_from_template  = (strtolower($allDataInSheet[0][1]) == "default" || strtolower($allDataInSheet[0][1]) == null) ? $fileName : $allDataInSheet[0][1];
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
					echo "Error creating database: " . $conn->error;
					echo "<br>";
				}
			}
			if($DB_Created){
				mysqli_select_db($conn,$DB_Info_from_template);
				$excel_mysqlt = new Excel_mysql($conn, $_FILES["fileToUpload"]["tmp_name"]);
				echo $excel_mysqlt->excel_to_mysql_by_index("fyit", 1, $allDataInSheet[3], $start_row_index = 2, false, false, false, $allDataInSheet[4]) ? "OK\n" : "FAIL\n";
			}


			// echo "<br>";
			// while($arrayCount>= $rowIndex){
			// 	echo json_encode($allDataInSheet[$rowIndex]);
			// 	echo "<br>";
			// 	$nullLineIndex++;
			// 	if($nullLineIndex==3){
			// 		$rowIndex++;
			// 		$nullLineIndex=0;
			// 	}
			// 	$rowIndex++;
			// }
		} catch(Exception $e) {
            die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
		}
		
	}else{
		echo "Please Upload only Excel sheet File";
	}
}
?>