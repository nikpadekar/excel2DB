<?php
require_once __DIR__ . "/PHPExcel/Classes/PHPExcel.php";
require_once __DIR__ . "/library/excel_mysql.php";
$target_dir = "uploads/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
// Check if image file is a actual image or fake image
$mimes = array('application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/xls','text/xlsx');
//var_dump($_FILES["fileToUpload"]);
if(isset($_POST["submit"])) {
	if(in_array($_FILES["fileToUpload"]["type"],$mimes)){
		echo "valid Excel Uploaded"."<br>";
		try{
			$objPHPExcel = PHPExcel_IOFactory::load($_FILES["fileToUpload"]["tmp_name"]);
			$allDataInSheet = $objPHPExcel->getSheetByName('templete')->toArray(null);
			$arrayCount = count($allDataInSheet);  // Here get total count of row in that Excel sheet
			$rowIndex=0;
			$nullLineIndex=0;
			while($arrayCount>= $rowIndex){
				echo json_encode($allDataInSheet[$rowIndex]);
				echo "<br>";
				$nullLineIndex++;
				if($nullLineIndex==3){
					$rowIndex++;
					$nullLineIndex=0;
				}
				$rowIndex++;
			}
		} catch(Exception $e) {
            die('Error loading file "'.pathinfo($inputFileName,PATHINFO_BASENAME).'": '.$e->getMessage());
		}
		
	}else{
		echo "Please Upload only Excel sheet File";
	}
}
?>