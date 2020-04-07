<?php
ini_set('max_execution_time', 300); //300 seconds = 5 minutes
set_time_limit(300);
include 'class/Leads.php';
include 'lead_data.php'; 

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
        
    $newArray                   = convertCSVtoArray($arr);
    $lead_data                  = leadMapLookUp($newArray,$lead_lookup,$lead_lookup_2);
    $date_range                 = getDateRange($lead_data);
    $lead_report                = generateLeadReport($lead_data,$date_range);
    $generate_total_lead_report = generateTotalLead($date_range);
    downloadSpendLeadCSVFile($lead_report);

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

function leadMapLookUp($newArray,$lead_lookup,$lead_lookup_2) {
  $lead = new Leads();
  $lead_map = array();
  for ($i=0; $i < count($newArray) ; $i++) {  
    if($newArray[$i]['BrandName']!='') {
      if($lead->existLead($newArray[$i]['CorrelationID'])==0) {
        $bname    = $newArray[$i]['BrandName'];   
        $fname    = $newArray[$i]['FirstName'];   
        $ds       = $newArray[$i]['DiscoveredSource'];
        $dso      = $newArray[$i]['DiscoveredSourceOther'];
        $kw       = $newArray[$i]['Keyword'];
        $corr_id  = $newArray[$i]['CorrelationID'];
        $leaddate = date("Y-m-d 00:00:00", strtotime($newArray[$i]['LeadDate']));
        $date     = explode('/', date("m/d/Y", strtotime($newArray[$i]['LeadDate'])));
        $m      = $date[0]; 
        $d      = $date[1];
        $yr     = $date[2];
        $w      = week_number($newArray[$i]['LeadDate']);
        $bname__c     = str_replace(' ', '', $bname);
        $ds__c        = str_replace(' ', '', $ds);
        $dso__c       = str_replace(' ', '', $dso);
        $kw__c        = str_replace(' ', '', $kw);
        $bn_ds_dso_kw = $bname__c.$ds__c.$dso__c.$kw__c;
        $lead_map[$i]['bname']    = $bname;
        $lead_map[$i]['fname']    = $fname;
        $lead_map[$i]['ds']     = $ds;
        $lead_map[$i]['dso']    = $dso;
        $lead_map[$i]['kw']     = $kw;
        $lead_map[$i]['corr_id']  = $corr_id;   
        
        $lead_map[$i]['lsf']    = (array_key_exists($bn_ds_dso_kw,$lead_lookup) ? $lead_lookup[$bn_ds_dso_kw][0] : '');
        $lead_map[$i]['fc']     = (array_key_exists($bn_ds_dso_kw,$lead_lookup) ? $lead_lookup[$bn_ds_dso_kw][1] : '');
        $lead_map[$i]['paid']   = (array_key_exists($bn_ds_dso_kw,$lead_lookup) ? $lead_lookup[$bn_ds_dso_kw][2] : '');
        $lead_map[$i]['ch']     = (array_key_exists($bn_ds_dso_kw,$lead_lookup) ? $lead_lookup[$bn_ds_dso_kw][3] : '');
        
        if($lead_map[$i]['lsf']=='') {
          if(array_key_exists($dso,$lead_lookup_2)) {
            $lead_map[$i]['lsf']  = $lead_lookup_2[$dso][0];
            $lead_map[$i]['fc']   = $lead_lookup_2[$dso][1];
            $lead_map[$i]['paid'] = $lead_lookup_2[$dso][2];
            $lead_map[$i]['ch']   = $lead_lookup_2[$dso][3];
          }
        }

        $lead_map[$i]['leaddate']   = $leaddate;         
        $lead->insertLead($bname,$fname,$ds,$dso,$kw,$corr_id,$lead_map[$i]['lsf'],$lead_map[$i]['fc'],$lead_map[$i]['paid'],$lead_map[$i]['ch'],$yr,$m,$d,$w,$leaddate);
      }
    }
  } 
  return $lead_map;
}

function week_number($date)  { 
  $firstOfMonth = date("Y-m-01", strtotime($date));
    return intval(strftime("%U", strtotime($date))) - intval(strftime("%U", strtotime($firstOfMonth))) + 1;
}

function getDateRange($newArray) {
  $getDate = array();
  for ($i=0; $i < count($newArray) ; $i++) {
    array_push($getDate, $newArray[$i]['leaddate']);
  }
  $unique_date = array_unique($getDate);
  sort($unique_date);
  $sort_date = array();
  for($i = 0; $i < count($unique_date); $i++) {
    $sort_date[$i] = $unique_date[$i];
  }

  return $sort_date;
}

function generateTotalLead($date_range) {

  $lr = new Leads();
  $microsites_list = [
    'BookPublishingCompanies.com',
    'Childrens Microsite',
    'ChooseYourPublisher.com',
    'FindYourPublisher.com',
    'FindYourPublisher.co.uk',
    'MD_PublisherMatch.org',
    'NewWriterPublisher.com'
  ];
  foreach ($date_range as $date) {
    $curr_date = date('Y-m-d', strtotime($date));
    foreach ($microsites_list as $microsite) {
      $hasreport = $lr->hasLeadReportToday($microsite,$date); // connect to db and check if there is lead report created yesterday
      if($hasreport==null) { // true if lead report was not created yesterday        
        $lr->getSummaryReport($microsite,$date,$curr_date);
      }
    }
  }
  
}



function generateLeadReport($lead_data,$date_range) {
  $lead = new Leads();
  $counter=0;
  $output_data = array();
  $imprint_list = $lead->getImprintList();
  $startdate  = $date_range[0];
  $enddate  = $date_range[count($date_range)-1];
  foreach($imprint_list as $data) {
    $brand   = $data['imprint_name__c'];
    $results = $lead->getNumberofLeadsByDSO($brand,$startdate,$enddate);
    $output_data[$counter] = $results;
    $counter++;
  }
  return $output_data;
}

function downloadSpendLeadCSVFile($generatedArray) {
  $report_data_length = count($generatedArray);
  $today = date("Y-m-d H_i_s");
  $fileName = 'Lead Actual Report'.$today.'.csv';
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header('Content-Description: File Transfer');
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename={$fileName}");
        header("Expires: 0");
        header("Pragma: public");
        $fh1 = @fopen( 'php://output', 'w' );
        $headerDisplayed1 = false;
    for($i=0;$i<$report_data_length;$i++) {
      if(count($generatedArray[$i])>0) {
        foreach ( $generatedArray[$i] as $data ) {
          // Add a header row if it hasn't been added yet
          if ( !$headerDisplayed1 ) {
            // Use the keys from $data as the titles
            fputcsv($fh1, array_keys($data));
            $headerDisplayed1 = true;
          }
    
          // Put the data into the stream
          fputcsv($fh1, $data);
        }
      }
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
          <h1 class="h3 mb-4 text-gray-800">Lead Actual Report Generator</h1>
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
                            <input class="form-control-file" type="file" name="file" id="file" accept=".csv"><br/>
                            <button type="submit" id="submit" name="import" class="btn btn-primary">Upload</button>
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