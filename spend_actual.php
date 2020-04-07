<?php
ini_set('max_execution_time', 300); //300 seconds = 5 minutes
set_time_limit(300);
include 'class/Leads.php';
include 'spend_data.php'; 

if (isset($_POST["import"])) {    
	$newArray = array();
	$generatedArray = array();

    $fileContent = $_FILES["file"]["tmp_name"];    //content of the file
    if ($_FILES["file"]["size"] > 0) {

		$filename =  $_FILES["file"]["name"];
        $filesize = ($_FILES["file"]["size"] / 1024) . "Kb";
        
        $file = fopen($fileContent, "r"); // view or read the file
        $i=0;
        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) { //get the data of every column in array method
            for ($j = 0; $j < count($column); $j++) { 
		        $arr[$i][$j] = $column[$j]; 
		    } 
		    $i++; 
        }
		$newArray 		= convertCSVtoArray($arr);
		$generatedArray = generatedData($newArray,$spend_lookup);
		downloadSpendLeadCSVFile($generatedArray);

    }
}

function convertCSVtoArray($data) {
    $count = count($data) - 1; 

    
	$labels = array_shift($data);  //remove the first column which is the key
	foreach ($labels as $label) {
	  $keys[] = $label;
	}
	  
	// Bring it all together
	for ($j = 0; $j < $count; $j++) { // join the key and the key value
	  $d = array_combine($keys, $data[$j]);
	  $newArray[$j] = $d;
	}
	return $newArray;
}

function generatedData($newArray,$spend_lookup) {
	$counter=0; // number of column generated column
	$lr = new Leads(); // connect to the DB lead report
	for ($i=0; $i < count($newArray) ; $i++) { 		
		if(in_array($newArray[$i]["Account name"], $spend_lookup["Microsite"])) { // check if the account name is Microsite	
			$temp_date = date("Y-m-d 00:00:00", strtotime($newArray[$i]['Day']));  // change the format of the date the same with the DB
	    	$temp_account_name = $spend_lookup["DSOMicrosite"][$newArray[$i]["Account name"]]; 
	    	
			$leads = $lr->getLeadsTodayForReporting($temp_account_name,$temp_date); // get the leads with the given date and account name
			$totalleads = $lr->getTotalLeadsTodayForReporting($temp_account_name,$temp_date); // get the total number of leads within the given date and account name
			if($totalleads[0]['total__c']>0) {
				$CPL = $newArray[$i]['Spend'] / $totalleads[0]['total__c']; // cost per lead total spend cost /  total number of leads in a account number
			}
			else {
				$CPL = $newArray[$i]['Spend'];
			}
			if($leads==null){
				$leads[0]['bname__c'] = '';
				$leads[0]['microsite'] = $newArray[$i]["Account name"];
				$leads[0]['leads'] = 1;
				$CPL = $newArray[$i]['Spend'];
			}

			foreach ($leads as $lead) {			
				$date = $newArray[$i]['Day'];
				$date = explode('/', $date);

				$br  = $lead['bname__c'];				
				$lsf = $spend_lookup["LeadSourceFinal"][$newArray[$i]["Account name"]];
				
				$fcn = $spend_lookup["FinalCampaignChannel"][$lsf][0];
				$pd  = 'Paid';
				$ch  = $spend_lookup["FinalCampaignChannel"][$lsf][1];
				$yr  = $date[2];
				$m   = $date[0]; 
				$d   = $date[1];
				$w   = week_number($newArray[$i]['Day']);
				$spend = $CPL * $lead['leads'];
				$generatedArray[$counter]['Brand'] = $br;
				$generatedArray[$counter]['Lead Source Final'] = $lsf;
				$generatedArray[$counter]['Final Campaign Name'] = $fcn;
				$generatedArray[$counter]['Paid/Non-Paid'] = $pd;
				$generatedArray[$counter]['Channel'] = $ch;
				$generatedArray[$counter]['Ykey'] = $yr;
				$generatedArray[$counter]['Mkey'] = $m;
				$generatedArray[$counter]['Dkey'] = $d;
				$generatedArray[$counter]['WkKey'] = $w;
				$generatedArray[$counter]['YYYYMMDDKey'] = $newArray[$i]['Day'];
				$generatedArray[$counter]['Spend Actual'] = $spend;
				$counter++;
			}
		}

		else {
			$date = $newArray[$i]['Day'];
			$date = explode('/', $date);

			$br  = $spend_lookup["Brandname"][$newArray[$i]["Account name"]];
			$lsf = $newArray[$i]['Source'] == "" ? $spend_lookup['LeadSourceFinal'][$newArray[$i]["Account name"]] : $newArray[$i]['Source'];
			$fcn = $newArray[$i]['Campaign name'] == "" ? $spend_lookup["FinalCampaignChannel"][$newArray[$i]["Account name"]][0] : $newArray[$i]['Campaign name'];
			$fcn = $fcn == "" ? $spend_lookup["FinalCampaignChannel"][$newArray[$i]["Account name"]][0] : $fcn;
			$pd  = 'Paid';
			$ch  = $newArray[$i]['Campaign name'] == "" ? $spend_lookup["FinalCampaignChannel"][$newArray[$i]["Account name"]][1] : $spend_lookup["FinalCampaignChannel"][$newArray[$i]["Campaign name"]][1];

			$yr  = $date[2];
			$m   = $date[0]; 
			$d   = $date[1];
			$w   = week_number($newArray[$i]['Day']);

			$generatedArray[$counter]['Brand'] = $br;
			$generatedArray[$counter]['Lead Source Final'] = $lsf;
			$generatedArray[$counter]['Final Campaign Name'] = $fcn;
			$generatedArray[$counter]['Paid/Non-Paid'] = $pd;
			$generatedArray[$counter]['Channel'] = $ch;
			$generatedArray[$counter]['Ykey'] = $yr;
			$generatedArray[$counter]['Mkey'] = $m;
			$generatedArray[$counter]['Dkey'] = $d;
			$generatedArray[$counter]['WkKey'] = $w;
			$generatedArray[$counter]['YYYYMMDDKey'] = $newArray[$i]['Day'];
			$generatedArray[$counter]['Spend Actual'] = $newArray[$i]['Spend'];
			$counter++;
		}

	}
	
	return $generatedArray;
}

function week_number($date)  { 
	$firstOfMonth = date("Y-m-01", strtotime($date));
    return intval(strftime("%U", strtotime($date))) - intval(strftime("%U", strtotime($firstOfMonth))) + 1;
}


function downloadSpendLeadCSVFile($generatedArray) {
	$today = date("Y-m-d H_i_s");
	$fileName = 'LeadReport'.$today.'.csv';
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header('Content-Description: File Transfer');
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename={$fileName}");
        header("Expires: 0");
        header("Pragma: public");
        $fh1 = @fopen( 'php://output', 'w' );
        $headerDisplayed1 = false;
 
        foreach ( $generatedArray as $data1 ) {
            // Add a header row if it hasn't been added yet
            if ( !$headerDisplayed1 ) {
                // Use the keys from $data as the titles
                fputcsv($fh1, array_keys($data1));
                $headerDisplayed1 = true;
            }
 
            // Put the data into the stream
            fputcsv($fh1, $data1);
        }
    // Close the file
        fclose($fh1);
    // Make sure nothing else is sent, our file is done
        exit;
}

?>
<?php include('layouts/header.php');   ?>

<!-- Sidebar -->
<?php include('layouts/sidebarmenu.php'); ?>    
<!-- End of Sidebar -->

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">

  <!-- Main Content -->
  <div id="content">
    <?php include 'layouts/navbar.php'; ?>
    <div class="container-fluid">
      <h1 class="h3 mb-4 text-gray-800">Spend Actual Report Generator</h1>
      <div class="row">
        <div class="col">
          <!-- Default Card Example -->
          <div class="card mb-4">
            <div class="card-header bg-gradient-primary"></div>
              <div class="card-body">
                <div class="form-group">
                  <form action="" method="post" name="uploadCSV" enctype="multipart/form-data">
					<div class="form-group">
					    <label>Choose CSV File</label>
					    <input class="form-control-file" type="file" name="file" id="file" accept=".csv">
					    <br />
					    <button type="submit" id="submit" name="import" class="btn btn-primary">Import</button>
					    <div id="labelError"></div>
                        </div>
                      </form> 
                    </div>
                  </div>
                </div>
              </div>
            </div>
        </div>
      </div>
      <!-- End of Main Content -->

      <?php include 'layouts/footer.php'; ?>