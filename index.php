<?php 
session_start();
require 'vendor/autoload.php';

$app = new \Slim\Slim(array(
    'view' => new \Slim\Views\Twig(array('debug' => true)),
    'debug' =>          true,
    'mode' =>           'development',
    'templates.path' => './templates',
));

$app->view->parserExtensions = [
        new \Slim\Views\TwigExtension(),
        new \Twig_Extension_Debug(array('debug' => true)),
        
    ];


$dsn 		    = 'mysql:dbname=katalog;host=localhost';
$user 		  = 'root';
$password 	= 'root';

$app->db = new PDO($dsn, $user, $password);
$app->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


function checkAdmin(){
        if ( !isset($_SESSION['login']) || ($_SESSION['login'] == 'true' && $_SESSION['level']!='admin')) {
            $app = \Slim\Slim::getInstance();
            $app->flash('error', 'Login required');
            $app->redirect('/login');
    };
};

$app->get('/' ,function() use($app){
	$sql = "SELECT buku.kode, buku.judul, buku.tahun, buku.jumlah, buku.cover, 
			penerbit.nama AS penerbit, penulis.nama AS penulis, kategori.nama AS kategori, rak.nama AS rak 
			FROM buku 
			LEFT JOIN penerbit ON penerbit.id = buku.penerbit_id
			LEFT JOIN penulis ON penulis.id = buku.penulis_id
			LEFT JOIN kategori ON kategori.id = buku.kategori_id
			LEFT JOIN rak ON rak.id = buku.rak_id";
	$stmt  = $app->db->prepare($sql);
	;
	if ($stmt->execute() == TRUE) {
		$buku = $stmt->fetchAll();
		$data['buku'] = $buku;
 		$app->view()->setData(array());	
		$app->render('buku.twig',$data);
	}
	
});


$app->get('/login', function () use ($app){
 	$app->render('login.twig');
	
});

$app->post('/login', function () use ($app) {   
 	$sql = "SELECT * FROM user WHERE id = :id AND password=:password";
 	$stmt  = $app->db->prepare($sql);
 	$stmt->bindParam(':id',$_POST['username']);
 	$stmt->bindParam(':password',sha1($_POST['password']));
 	$stmt->execute();
 	if($stmt->rowCount() == 1){
 		$result = $stmt->fetch(PDO::FETCH_ASSOC);
 		$_SESSION['login']='true';
 		$_SESSION['name'] = $result['nama'];
 		$_SESSION['level'] = $result['level'];
 		$app->flash('success', "Login Success");
        $app->redirect('/admin');
 	}else{
 		$app->flash('error', "Login Failed");
        $app->redirect('/login');
 	}
 	
});

$app->get('/logout',function()use ($app){
	session_destroy();
	$app->flash('error', "Logout");
    $app->redirect('/login');
});

$app->get('/admin','checkAdmin' ,function() use($app){
	$sql = "SELECT buku.kode, buku.judul, buku.tahun, buku.jumlah, buku.cover, 
			penerbit.nama AS penerbit, penulis.nama AS penulis, kategori.nama AS kategori, rak.nama AS rak 
			FROM buku 
			LEFT JOIN penerbit ON penerbit.id = buku.penerbit_id
			LEFT JOIN penulis ON penulis.id = buku.penulis_id
			LEFT JOIN kategori ON kategori.id = buku.kategori_id
			LEFT JOIN rak ON rak.id = buku.rak_id";
	$stmt  = $app->db->prepare($sql);
	;
	if ($stmt->execute() == TRUE) {
		$buku = $stmt->fetchAll();
		$data['buku'] = $buku;
    $data['message']= array('nama'=>$_SESSION['name']);
 		$app->view()->setData(array());	
		$app->render('admin/index.twig',$data);
	}
});

$app->get('/import','checkAdmin',function() use($app){
  $data['message']= array('nama'=>$_SESSION['name']);
  $app->render('admin/import.twig',$data);
});

$app->post('/import','checkAdmin',function() use($app){
  try{
    $file = $_FILES['uploadedFile']['tmp_name'];
    $data_file = PHPExcel_IOFactory::identify($file);
    $objReader = PHPExcel_IOFactory::createReader($data_file);
    $objPHPExcel = $objReader->load($file);
  }catch(Exception $e){
    die($e->getMessage());
  }

  //query simpan
  $sql ="INSERT INTO buku (kode,judul,penerbit_id,penulis_id,kategori_id,tahun,rak_id,jumlah,cover) 
          VALUES (:kode,:judul,:penerbit,:penulis,:kategori,:tahun,:rak,:jumlah,:cover)";
  $stmt  = $app->db->prepare($sql);

  //jika memilih isi range sheet
  //$rowData = $objPHPExcel->getActiveSheet()->rangeToArray('B3:J12', NULL, True, True);
  
  //jika memilih seluruh isi sheet
  $sheet = $objPHPExcel->getActiveSheet(); 
  $highestRow = $sheet->getHighestRow(); 
  $highestColumn = $sheet->getHighestColumn();
  $colNumber = PHPExcel_Cell::columnIndexFromString($highestColumn);

  $rowData = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow,NULL,TRUE,FALSE);
  for ($i=0; $i < $colNumber; $i++) { 
    try {
      $stmt->execute(array(
        ':kode'=>$rowData[$i+1][0],
        ':judul'=>$rowData[$i+1][1],
        ':penerbit'=>$rowData[$i+1][2],
        ':penulis'=>$rowData[$i+1][3],
        ':kategori'=>$rowData[$i+1][4],
        ':tahun'=>$rowData[$i+1][5],
        ':rak'=>$rowData[$i+1][6],
        ':jumlah'=>$rowData[$i+1][7],
        ':cover'=>$rowData[$i+1][8],
      ));
    } catch (Exception $e) {
      $error = array();
      $error[] = $e->getMessage();
    }
    $data['kesalahan']= array('kesalahan'=>$error);
  }

  $data['preview']= $rowData;
  $data['message']= array('nama'=>$_SESSION['name']);
  $app->render('admin/import.twig',$data);
});

$app->run();