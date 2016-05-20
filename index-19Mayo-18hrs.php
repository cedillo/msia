<?php
	session_start();
	require 'src/conexion.php';
	require 'Slim/Slim.php';
	include 'Excel/simplexlsx.class.php';


	\Slim\Slim::registerAutoloader();
	$app = new \Slim\Slim();
	
	$db="";
	
	require 'hector.php';
	//require 'arian.php';
	require 'cota.php';	
	
	

	define("MAIN_ACCESS", true);

	$app->config(array('debug'=>true, 'templates.path'=>'./',));

	try{
		$db = new PDO("sqlsrv:Server={$hostname}; Database={$database}", $username, $password );
	}catch (PDOException $e) {
		print "ERROR: " . $e->getMessage() . "<br><br>HOSTNAME: " . $hostname . " BD:" . $database . " USR: " . $username . " PASS: " . $password . "<br><br>";
		die();
	}
	if(!isset($_SESSION["logueado"])) $_SESSION["logueado"]=0;
	
	
	

	//ACCESO AL SISTEMA

	//Acceso al sitio
	$app->get('/', function() use($app){
		if($_SESSION["logueado"]==1){
			$result= array('idUsuario' => $_SESSION["idUsuario"] , 'nombre' => $_SESSION["sUsuario"] );
			$app->render('dashboard.php', $result);
		}else{
			$app->render('login.html');
		}
	});

	//Login
	$app->post('/login', function()  use($app, $db) {
		$request=$app->request;
		$cuenta = $request->post('txtUsuario');
		$pass = $request->post('txtPass');
		$latitud = $request->post('txtLatitud');
		$longitud = $request->post('txtLongitud');

		
		$sql = "SELECT u.idUsuario, u.idEmpleado, CONCAT(u.nombre, ' ', u.paterno, ' ', u.materno) nombre, e.idArea, p.nombre sPlaza " .
		"FROM sia_usuarios u " .
		"inner join sia_empleados e on u.idEmpleado=e.idEmpleado " . 
		"inner join sia_plazas p on e.idArea = p.idArea and e.idNivel = p.idNivel and e.idNombramiento = p.idNombramiento and e.idPuesto = p.idPuesto and e.idPlaza = p.idPlaza " .
		" WHERE usuario=:cuenta and pwd=:pass";
		
		
		//$dbQuery = $db->prepare("SELECT idUsuario, rpe, CONCAT(nombre, ' ', paterno, ' ', materno) nombre FROM sia_usuarios WHERE usuario=:cuenta and pwd=:pass");
		$dbQuery = $db->prepare($sql);
		
		$dbQuery->execute(array(':cuenta' => $cuenta, ':pass' => $pass));
		$result = $dbQuery->fetch(PDO::FETCH_ASSOC);
		if($result){

		$_SESSION["logueado"] =1;
		$_SESSION["idUsuario"] =$result['idUsuario'];
		$_SESSION["sUsuario"] =$result['nombre'];
		
		$_SESSION["idEmpleado"] =$result['idEmpleado'];
		$_SESSION["idArea"] =$result['idArea'];
		$_SESSION["sPlaza"] =$result['sPlaza'];


		//Obtener datos generales
		//$_SESSION["idEntidad"] =9;
		$sql="SELECT idEntidad  FROM sia_configuracion";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute();
		$result = $dbQuery->fetch(PDO::FETCH_ASSOC);
		if($result){
			$_SESSION["idEntidad"] = $result['idEntidad'];
		}else{
			$_SESSION["idEntidad"] =99;
		}

		//Registrar acceso
		$usrActual = $_SESSION["idUsuario"];
		$sql="INSERT INTO sia_accesos (idUsuario, fIngreso, latitud, longitud) VALUES(:usrActual, getdate(), :latitud, :longitud);";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':usrActual' => $usrActual, ':latitud'=>$latitud, ':longitud'=>$longitud ));

		$sql="SELECT idAcceso FROM sia_accesos WHERE idUsuario= :usrActual ORDER BY idAcceso desc ";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':usrActual'=> $usrActual));
		$result = $dbQuery->fetch(PDO::FETCH_ASSOC);
		$id = $result['idAcceso'];


		//Obtener la campaña actual
		$sql = 	"SELECT c.idCuenta id, c.nombre FROM sia_cuentas c inner join sia_cuentausuario cu  on c.idCuenta=cu.idCuenta WHERE cu.predeterminada='SI' and cu.idUsuario =:idx";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':idx'=>$_SESSION["idUsuario"]));
		$result = $dbQuery->fetch(PDO::FETCH_ASSOC);

		if($result){
			$_SESSION["sCuentaActual"] 		=$result['nombre'];
			$_SESSION["idCuentaActual"] 	=$result['id'];
			$tmpCta=$result['id'];

			//Obtener el PGA actual
			$sql = 	"SELECT idPrograma FROM sia_programas WHERE idCuenta=:cta";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cta'=> $tmpCta ));
			$result = $dbQuery->fetch(PDO::FETCH_ASSOC);
			$_SESSION["idProgramaActual"] 	=$result['idPrograma'];


		}else{
			$_SESSION["sCuentaActual"] 		="";
			$_SESSION["idCuentaActual"] 	="";
			$_SESSION["idProgramaActual"] 	="***";

		}
		$app->render('dashboard.php');
		}else{
			$app->halt(404, "Usuario: " . $cuenta . " Pass: " . $pass . "<br>USUARIO NO ENCONTRADO.");
			$_SESSION["logueado"] =0;
			$app->render('login.html');
		}
	});

	$app->get('/cerrar', function() use($app, $db){

			$sql="UPDATE sia_accesos SET fEgreso=getdate(), estatus='INACTIVO' WHERE idUsuario=:usrActual AND estatus='ACTIVO';";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':usrActual'=>$_SESSION["idUsuario"]));


		//unset($_SESSION["idUsuario"]);
		//unset($_SESSION["sUsuario"]);
		session_destroy();
		$app->render('login.html');
	});


	$app->get('/dashboard', function()  use ($app) {
		$app->render('dashboard.php');
	});





	////////////////////////////////////////////////////////////////////////////////////////////

	$app->get('/acopio', function() use($app, $db){
		$cuenta = $_SESSION["idCuentaActual"];

		//$sql="SELECT distinct idCuenta, idPrograma FROM sia_programas WHERE idCuenta=:cuenta ";

		$sql="SELECT ac.idCuenta, ac.idPrograma, ac.idAuditoria auditoria,  s.nombre sujeto, o.nombre objeto, ac.idAcopio id, ac.clasificacion, ac.asunto, ta.nombre tipo, " .
		"CONVERT(VARCHAR(11),ac.fAlta,102) fecha, ac.idFase fase, ac.tipoArchivo, ac.estatus " .
		"FROM sia_acopio ac " .
		"LEFT JOIN sia_sujetos s on ac.idSujeto=s.idSujeto " .
		"LEFT JOIN sia_objetos o on ac.idSujeto=o.idSujeto and ac.idObjeto=o.idObjeto " .
		"LEFT JOIN sia_auditorias a on ac.idAuditoria=a.idAuditoria " .
		"LEFT JOIN sia_tiposauditoria ta on a.tipoAuditoria=ta.idTipoAuditoria " .
		"ORDER BY  ac.idAcopio DESC";

		$dbQuery = $db->prepare($sql);

		$dbQuery->execute(array(':cuenta' => $cuenta));

		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);

		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS.");
		}else{
			$app->render('acopio.php', $result);
		}


	});

	/*
	$app->get('/catAuditores', function()  use ($app) {
		$app->render('catAuditores.php');
	});
*/











	$app->get('/papeles', function()  use ($app, $db) {
		$cuenta = $_SESSION["idCuentaActual"];

		//$sql="SELECT distinct idCuenta, idPrograma FROM sia_programas WHERE idCuenta=:cuenta ";

		$sql="SELECT p.idCuenta, p.idPrograma, p.idAuditoria auditoria,  s.nombre sujeto, o.nombre objeto, p.idPapel, p.tipoPapel, p.tipoResultado, p.resultado, ta.nombre tipo, " .
		"CONVERT(VARCHAR(12),p.fAlta,102) fechaPapel, p.idFase fase, p.tipoPapel, p.tipoResultado, p.resultado, p.estatus  " .
		"FROM sia_papeles p   " .
		"LEFT JOIN sia_sujetos s on p.idSujeto=s.idSujeto " .
		"LEFT JOIN sia_objetos o on p.idSujeto=o.idSujeto and p.idObjeto=o.idObjeto  " .
		"LEFT JOIN sia_auditorias a on p.idAuditoria=a.idAuditoria " .
		"LEFT JOIN sia_tiposauditoria ta on a.tipoAuditoria=ta.idTipoAuditoria  " .
		"ORDER BY  p.idPapel DESC";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':cuenta' => $cuenta));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS.");
		}else{
			$app->render('papeles.php', $result);
		}
	})->name('listaPapeles');





	$app->get('/avances', function()  use ($app) {

		$app->render('avances.php');
	});

	$app->get('/avanceActividad', function()  use ($app, $db) {
		$cuenta = $_SESSION["idCuentaActual"];

		//$sql="SELECT distinct idCuenta, idPrograma FROM sia_programas WHERE idCuenta=:cuenta ";

		$sql="SELECT aa.idCuenta, aa.idPrograma, aa.idAuditoria auditoria,  s.nombre sujeto, o.nombre objeto, aas.descripcion actividad, ta.nombre tipo, aa.idAvance avance, " .
		"CONVERT(VARCHAR(12),aa.fAlta,102) fechaAvance, aa.idFase fase, aa.porcentaje, aa.estatus " .
		"FROM sia_auditoriasavances aa " .
		"LEFT JOIN sia_sujetos s on s.idSujeto=aa.idSujeto " .
		"LEFT JOIN sia_objetos o on o.idSujeto=aa.idSujeto and o.idObjeto=aa.idObjeto " .
		"LEFT JOIN sia_auditoriasactividades aas on aa.idActividad=aas.idActividad " .
		"LEFT JOIN sia_auditorias a on aa.idAuditoria=a.idAuditoria " .
		"LEFT JOIN sia_tiposauditoria ta on a.tipoAuditoria=ta.idTipoAuditoria " .
		"ORDER BY  aa.idAvance DESC";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':cuenta' => $cuenta));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS.");
		}else{
			$app->render('avanceActividad.php', $result);
		}
	})->name('listaAvances');


	$app->get('/auditorias', function()  use($app, $db) {
		$sql="SELECT a.idAuditoria auditoria, ar.nombre area, s.nombre sujeto, o.nombre objeto, a.tipoAuditoria tipo, '0.00' avances " .
		"FROM sia_programas p " .
		"INNER JOIN sia_auditorias a on p.idCuenta=a.idCuenta and p.idPrograma=a.idPrograma " .
		"LEFT JOIN sia_areas ar on a.idArea=ar.idArea " .
		"LEFT JOIN sia_sujetos s on a.idSujeto=s.idSujeto " .
		"LEFT JOIN sia_objetos o on a.idSujeto=o.idSujeto and a.idObjeto=o.idObjeto " .
		"ORDER BY ar.nombre, s.nombre, o.nombre, a.tipoAuditoria ";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute();
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS.");
		}else{
			$app->render('auditorias.php', $result);
		}
	})->name('listaAuditorias');

	$app->get('/catUsuarios', function()  use ($app) {
		$app->render('catUsuarios.php');
	});

	$app->get('/catProcesos', function()  use ($app) {
		$app->render('catProcesos.php');
	});

	$app->get('/catFolios', function()  use ($app) {
		$app->render('catFolios.php');
	});

	$app->get('/catDocumentos', function()  use ($app) {
		$app->render('catDocumentos.php');
	});

	$app->get('/acciones', function()  use ($app) {
		$app->render('acciones.php');
	});

	$app->get('/catObjetos', function()  use ($app) {
		$app->render('catObjetos.php');
	});

	$app->get('/catCuentas', function()  use ($app, $db) {
		$sql="SELECT idCuenta id, nombre, fInicio inicio, fFin fin, estatus FROM sia_cuentas ORDER BY anio";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute();
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS.");
		}else{
			$app->render('catCuentas.php', $result);
		}
	})->name('listaCuentas');

	$app->get('/lstCuentasByID/:id', function($id)    use($app, $db) {
		$sql="SELECT idCuenta id, anio, nombre, fInicio inicio, fFin fin,  observaciones, estatus FROM sia_cuentas  Where idCuenta=:id ORDER BY anio";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result = $dbQuery->fetch(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});



	$app->get('/lstPapeles', function()    use($app, $db) {
		$sql="SELECT tp.idTipoPapel id, tp.nombre texto FROM sia_papelesfases pf INNER JOIN sia_tipospapeles tp on pf.idTipoPapel= tp.idTipoPapel ORDER BY tp.nombre";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array());
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});

	$app->get('/lstPapelesByFase/:id', function($id)    use($app, $db) {
		$sql="SELECT tp.idTipoPapel id, tp.nombre texto FROM sia_papelesfases pf INNER JOIN sia_tipospapeles tp on pf.idTipoPapel= tp.idTipoPapel WHERE pf.idFase=:id ORDER BY tp.nombre";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}

	});
	






	//Guarda un papel
	$app->post('/guardar/papel', function()  use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];

		$request=$app->request;
		$id = $request->post('txtPapel');
		$oper = $request->post('txtOperacion');
		$cuenta = $request->post('txtCuenta');
		$programa = $request->post('txtPrograma');
		$auditoria = $request->post('txtAuditoria');
		$sujeto = $request->post('txtSujeto');
		$objeto = $request->post('txtObjeto');
		$fase = $request->post('txtFase');
		$tipoPapel = $request->post('txtTipoPapel');
		$tipoResultado = $request->post('txtTipoRes');
		$resultado = strtoupper($request->post('txtResultado'));

		if($oper=='INS'){
			$sql="INSERT INTO sia_papeles (idCuenta, idPrograma, idAuditoria, idSujeto, idObjeto, idFase, tipoPapel, tipoResultado, resultado,  usrAlta, fAlta, estatus) " .
			"VALUES(:cuenta, :programa, :auditoria, :sujeto, :objeto, :fase, :tipoPapel, :tipoResultado, :resultado, :usrActual, getdate(), 'ACTIVO');";

			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cuenta' => $cuenta, ':programa' => $programa, ':auditoria' => $auditoria, ':sujeto' => $sujeto, ':objeto' => $objeto, ':fase' => $fase, ':tipoPapel' => $tipoPapel,':tipoResultado' => $tipoResultado,':resultado' => $resultado, ':usrActual' => $usrActual ));
		}else{
			$sql="UPDATE sia_papeles SET " .
			"idCuenta=:cuenta, idPrograma=:programa, idAuditoria=:auditoria, idSujeto=:sujeto, idObjeto=:objeto, idFase=:fase, tipoPapel=:tipoPapel, tipoResultado=:tipoResultado, resultado=:resultado, " .
			"usrModificacion=:usrActual, fModificacion=now() " .
			"WHERE idPapel=:id";

			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cuenta' => $cuenta, ':programa' => $programa, ':auditoria' => $auditoria, ':sujeto' => $sujeto, ':objeto' => $objeto, ':fase' => $fase, ':tipoPapel' => $tipoPapel,':tipoResultado' => $tipoResultado,':resultado' => $resultado, ':id' => $id  ));
		}
		$app->redirect($app->urlFor('listaPapeles'));
	});



	

	$app->get('/lstAuditoriaByID/:id', function($id)    use($app, $db) {
		$sql="SELECT idCuenta cuenta, idPrograma programa, idAuditoria auditoria,  tipoAuditoria tipo, idArea area, idSujeto sujeto, idObjeto objeto, objetivo, alcance, justificacion " .
		"FROM sia_auditorias  WHERE idAuditoria=:id ";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result = $dbQuery->fetch(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});

	
	
	

	$app->get('/lstCriteriosByAuditoria/:id', function($id)    use($app, $db) {
	
		$sql="Select  c.idcriterio id, c.nombre from sia_auditoriascriterios ac inner join sia_criterios c on ac.idCriterio=c.idCriterio " .
		"WHERE ac.idAuditoria=:id ";


	$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result = $dbQuery->fetch(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});	



	$app->get('/tblSujetosByCuenta/:id', function($id)    use($app, $db) {
		$sql="SELECT  s.idSujeto id, ltrim(s.nombre) sujeto, s.estatus " .
		"FROM sia_cuentas c " .
		"INNER JOIN sia_sujetos s on c.idCuenta=s.idCuenta " .
		"WHERE c.idCuenta= :id " .
		"ORDER BY s.nombre";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});


	$app->get('/tblObjetosByCuenta/:id', function($id)    use($app, $db) {
		$sql="SELECT  ltrim(s.nombre) sujeto, o.nombre objeto, o.original, o.modificado, o.ejercido, o.pagado, o.pendiente " .
		"FROM sia_cuentas c " .
		"INNER JOIN sia_sujetos s on c.idCuenta=s.idCuenta " .
		"INNER JOIN sia_objetos o on s.idCuenta=o.idCuenta and s.idSujeto=o.idSujeto " .
		"WHERE c.idCuenta= :id " .
		"ORDER BY s.nombre, o.nombre";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});


	$app->get('/tblActividadesByAuditoria/:id', function($id)    use($app, $db) {
		$sql="SELECT idFase fase, descripcion actividad, fInicio inicio, fFin fin, porcentaje, idPrioridad prioridad  FROM sia_auditoriasactividades  WHERE idAuditoria=:id ";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});


	//Guarda un avanceActividad
$app->post('/guardar/avance', function()  use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];

		$request=$app->request;
		$oper = $request->post('txtOperacion');
		$cuenta = $request->post('txtCuenta');
		$programa = $request->post('txtPrograma');
		$auditoria = $request->post('txtAuditoria');
		$sujeto = $request->post('txtSujeto');
		$objeto = $request->post('txtObjeto');
		$fase = $request->post('txtFase');
		$actividad = $request->post('txtActividad');


		$porcentaje = $request->post('txtPorcentaje');

		if($oper=='INS'){
			$sql="INSERT INTO sia_auditoriasavances (idCuenta, idPrograma, idAuditoria, idSujeto, idObjeto, idFase, idActividad, porcentaje,  usrAlta, fAlta, estatus) " .
			"VALUES(:cuenta, :programa, :auditoria, :sujeto, :objeto, :fase, :actividad, :porcentaje, :usrActual, getdate(), 'ACTIVO');";

			$dbQuery = $db->prepare($sql);

			$dbQuery->execute(array(':cuenta' => $cuenta, ':programa' => $programa, ':auditoria' => $auditoria,
			':sujeto' => $sujeto, ':objeto' => $objeto, ':fase' => $fase, ':actividad' => $actividad,':porcentaje' => $porcentaje, ':usrActual' => $usrActual ));
		}else{
			$avance = $request->post('txtAvance');
			$sql="UPDATE sia_auditoriasavances SET " .
			"idCuenta=:cuenta, idPrograma=:programa, idAuditoria=:auditoria, idSujeto=:sujeto, idObjeto=:objeto, idFase=:fase, idActividad=:actividad, porcentaje=:porcentaje, " .
			"usrModificacion=:usrActual, fModificacion=now() " .
			"WHERE idAvance=:avance";

			$dbQuery = $db->prepare($sql);

			$dbQuery->execute(array(':cuenta' => $cuenta, ':programa' => $programa, ':auditoria' => $auditoria, ':sujeto' => $sujeto, ':objeto' => $objeto,
			':fase' => $fase, ':actividad' => $actividad,':porcentaje' => $porcentaje, ':usrActual' => $usrActual, ':avance' => $avance ));
		}
		$app->redirect($app->urlFor('listaAvances'));
	});


	//Guardar una auditoria

	$app->post('/guardar/auditoria', function()  use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];

		$request=$app->request;
		$cuenta = $request->post('txtCuenta');
		$programa = $request->post('txtPrograma');
		$auditoria = $request->post('txtAuditoria');

		$oper = $request->post('txtOperacion');
		$tipo = $request->post('txtTipoAuditoria');
		$area = $request->post('txtArea');
		$sujeto = $request->post('txtSujeto');
		$objeto = $request->post('txtObjeto');
		$objetivo = strtoupper($request->post('txtObjetivo'));
		$alcance = strtoupper($request->post('txtAlcance'));
		$justificacion = strtoupper($request->post('txtJustificacion'));

		if($oper=='INS'){

			$cuenta = $_SESSION["idCuentaActual"];


			$auditoria = 'ASCM-' . date("YmdHis");

			$sql="INSERT INTO sia_auditorias (idCuenta, idPrograma, idArea, idAuditoria, tipoAuditoria, idSujeto, idObjeto, objetivo, alcance, justificacion, usrAlta, fAlta, estatus) ".
			"VALUES(:cuenta, :programa, :area, :auditoria, :tipo, :sujeto, :objeto, :objetivo, :alcance, :justificacion,  :usrActual, getdate(), 'ACTIVO');";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cuenta' => $cuenta, ':programa' => $programa,':area' => $area, ':auditoria' => $auditoria, ':tipo' => $tipo,
			':sujeto' => $sujeto, ':objeto' => $objeto,	 ':objetivo' => $objetivo, ':alcance' => $alcance, ':justificacion' => $justificacion, ':usrActual' => $usrActual ));

			//echo "<hr>INSERTA AUDITORIA: " . $auditoria;

		}else{
			$sql="UPDATE sia_auditorias " .
			"SET tipoAuditoria=:tipo, idArea=:area,  idSujeto=:sujeto, idObjeto=:objeto, objetivo=:objetivo, alcance=:alcance, justificacion=:justificacion, usrModificacion=:usrActual, fModificacion=now() " .
			"WHERE idAuditoria=:auditoria";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':tipo' => $tipo, ':area' => $area, ':sujeto' => $sujeto, ':objeto' => $objeto,	':objetivo' => $objetivo, ':alcance' => $alcance, ':justificacion' => $justificacion, ':usrActual' => $usrActual, ':auditoria' => $auditoria ));
		}
		$app->redirect($app->urlFor('listaPrograma'));
	});


// DATO
	$app->get('/guardar/auditoria/actividad/:oper/:cadena',  function($oper, $cadena)  use($app, $db) {
		$datos= $cadena;
		$usrActual = $_SESSION["idUsuario"];
		try{
			if($datos<>""){
				$dato = explode("|", $datos);
				$cuenta=$dato[0];
				$programa=$dato[1];
				$auditoria=$dato[2];
				$actividad=$dato[3];
				$tipo=$dato[4];
				$fase=$dato[5];
				$descripcion=strtoupper($dato[6]);
				$previa=$dato[7];

				$inicio = date_create($dato[8]);
				$inicio = $inicio->format('Y-m-d');

				$fin = date_create($dato[9]);
				$fin = $fin->format('Y-m-d');

				$porcentaje=$dato[10];
				$prioridad=$dato[11];
				$impacto=$dato[12];
				$responsable=$dato[13];
				$estatus=$dato[14];
				$notas=strtoupper($dato[15]);

				if ($oper=='INS-ACT'){
					$sql="INSERT INTO sia_auditoriasactividades (" .
					"idCuenta, idPrograma, idAuditoria, idFase,idTipo, descripcion, idActividadPrevia, fInicio, fFin, porcentaje, idPrioridad, idImpacto, notas, idResponsable, usrAlta, fAlta, estatus) " .
					"values(:cuenta, :programa, :auditoria, :fase, :tipo, :descripcion, :previa,  :inicio, :fin, :porcentaje, :prioridad, :impacto,:notas,:responsable,:usrAlta,getdate(),'ACTIVO')";
					$dbQuery = $db->prepare($sql);
					$dbQuery->execute(array(':cuenta' => $cuenta,':programa' => $programa,':auditoria' => $auditoria,':fase' => $fase,':tipo' => $tipo,':descripcion' => $descripcion,
					':previa' => $previa, ':inicio' => $inicio,':fin' => $fin,':porcentaje' => $porcentaje,':prioridad' => $prioridad,':impacto' => $impacto,
					':notas' => $notas,':responsable' => $responsable,':usrAlta' => $usrActual));
					echo "OK";
				}
				else {

				}
			}
			else{
				echo "NO";
			}
		}catch (Exception $e) {
				print "<br>¡Error en el TRY!: " . $e->getMessage();
				die();
			}



	});




	$app->post('/guardar/catCuentas', function()  use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];

		$request=$app->request;
		$id = $request->post('txtID');
		$oper = $request->post('txtOperacion');
		$anio = $request->post('txtAnio');
		$nombre = strtoupper($request->post('txtNombre'));
		$inicio = date_create(($request->post('txtFechaInicio')));
		$inicio = $inicio->format('Y-m-d');
		$fin = date_create(($request->post('txtFechaFin')));
		$fin = $fin->format('Y-m-d');

		$obs = strtoupper($request->post('txtNotas'));



		if($oper=='INS'){
			$cuenta = "CTA-" . $anio ;
			$sql="INSERT INTO sia_cuentas (idCuenta, anio, nombre, fInicio, fFin, observaciones, usrAlta, fAlta, estatus) ".
			"VALUES(:cuenta, :anio, :nombre, :inicio, :fin, :obs, :usrActual, getdate(), 'ACTIVO');";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cuenta' => $cuenta, ':anio' => $anio, ':nombre' => $nombre, ':inicio' => $inicio, ':fin' => $fin, ':obs' => $obs,':usrActual' => $usrActual ));

			//Crea un PGA nuevo para esta cuenta
			$programa = 'PGA-' . $cuenta;
			$sql="INSERT INTO sia_programas (idCuenta, idPrograma, fRegistro, usrAlta, fAlta, estatus) VALUES(:cuenta, :programa, getdate(), :usrActual, getdate(), 'ACTIVO');";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cuenta' => $cuenta, ':programa' => $programa, ':usrActual' => $usrActual ));

		}else{
			$cuenta = $id;
			$sql="UPDATE sia_cuentas SET anio=:anio, nombre=:nombre, fInicio=:inicio, fFin=:fin, observaciones=:obs, usrModificacion=:usrActual, fModificacion=now() WHERE idCuenta=:cuenta";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':anio' => $anio, ':nombre' => $nombre, ':inicio' => $inicio, ':fin' => $fin, ':obs' => $obs,':usrActual' => $usrActual,':cuenta' => $cuenta ));
		}
		$app->redirect($app->urlFor('listaCuentas'));
	});


	$app->get('/catSujetos', function()  use ($app) {
		$app->render('catSujetos.php');
	});


	$app->get('/programas', function()  use($app, $db) {
		$sql="SELECT a.idAuditoria auditoria, ar.nombre area, s.nombre sujeto, o.nombre objeto, a.tipoAuditoria tipo " .
		"FROM sia_programas p " .
		"INNER JOIN sia_auditorias a on p.idCuenta=a.idCuenta and p.idPrograma=a.idPrograma " .
		"LEFT JOIN sia_areas ar on a.idArea=ar.idArea " .
		"LEFT JOIN sia_sujetos s on a.idSujeto=s.idSujeto " .
		"LEFT JOIN sia_objetos o on a.idSujeto=o.idSujeto and a.idObjeto=o.idObjeto " .
		"ORDER BY ar.nombre, s.nombre, o.nombre, a.tipoAuditoria  ";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute();
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS.");
		}else{
			$app->render('programas.php', $result);
		}
	})->name('listaPrograma');

		$app->get('/tblGastoByUnidad/:sector/:subsector/:unidad', function($sector, $subsector, $unidad)    use($app, $db) {
		$cuenta = $_SESSION["idCuentaActual"];
		try{
			$sql="SELECT  f.nombre funcion, subfuncion, actividad, capitulo, partida " .
			"FROM sia_cuentasdetalles cd " .
			"left join sia_funciones f on f.idCuenta=cd.idCuenta and cd.sector=f.idSector and cd.subsector=f.idSubsector and f.idUnidad=cd.unidad " .
			"WHERE cd.idCuenta=:cuenta and cd.sector=:sector and cd.subsector=:subsector and cd.unidad=:unidad " .
			"ORDER BY funcion, subfuncion, actividad, capitulo, partida";

			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cuenta' => $cuenta, ':unidad' => $unidad));
			$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
			if(!$result){
				$app->halt(404, "NO SE ENCONTRARON DATOS ");
			}else{
				echo json_encode($result);
			}
		}catch (PDOException $e) {
			print "¡Error!: " . $e->getMessage() . "<br/>";
			die();
		}

	});


	





	//Lista de Areas
	$app->get('/lstAreas', function()    use($app, $db) {

		$sql="SELECT idArea id, nombre texto FROM sia_areas order by nombre";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute();
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});


	//Lista de sectores
	$app->get('/lstSectores', function()    use($app, $db) {
		$cuenta = $_SESSION["idCuentaActual"];

		$sql="SELECT ltrim(idSector) id, ltrim(nombre) texto FROM sia_sectores Where idCuenta=:cuenta ORDER BY nombre";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':cuenta' => $cuenta));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});

	//Lista de SUB-sectores
	$app->get('/lstSubsectoresBySector/:sector', function($sector)    use($app, $db) {
		$cuenta = $_SESSION["idCuentaActual"];

		$sql="SELECT ltrim(idSubsector) id, ltrim(nombre) texto FROM sia_subsectores Where idCuenta=:cuenta and idSector=:sector ORDER BY nombre";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':cuenta' => $cuenta, ':sector' => $sector));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});

	
	//Lista de unidades by
	$app->get('/lstUnidadesBySectorSubsector/:sector/:subsector', function($sector, $subsector)    use($app, $db) {
		$cuenta = $_SESSION["idCuentaActual"];
		$area = $_SESSION["idArea"];
		
		$sql="SELECT ltrim(idUnidad) id, nombre texto FROM sia_unidades WHERE idCuenta=:cuenta AND idSector=:sector AND idSubsector=:subsector ORDER BY nombre;";
		
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':cuenta' => $cuenta, ':sector' => $sector, ':subsector' => $subsector));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});


	//Lista de sujetos
	$app->get('/lstSujetos', function()    use($app, $db) {
		$sql="SELECT ltrim(idSujeto) id, concat(ltrim(idSujeto), ' ', nombre) texto FROM sia_sujetos order by nombre";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute();
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});

	//Lista de tipos de auditorias
	$app->get('/lstTiposAuditorias', function()    use($app, $db) {

		$sql="SELECT idTipoAuditoria id, nombre texto FROM sia_tiposAuditoria order by nombre";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute();
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});

	//Listar objetos by sujeto
	$app->get('/lstObjetosBySujeto/:id', function($id)  use($app, $db) {

		$sql="SELECT idObjeto id, nombre texto FROM sia_objetos Where idSujeto=:id order by nombre";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON OBJETOS DE FISCALIZACIÓN. ");
		}else{
			echo json_encode($result);
		}
	});

	//Listar auditorias by sujeto
	$app->get('/lstAuditoriasBySujeto/:id', function($id)  use($app, $db) {
		$sql="SELECT idAuditoria id, concat(idAuditoria, ' ', tipoAuditoria) texto FROM sia_auditorias Where idSujeto=:id order by 2 asc";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON AUDITORIAS. ");
		}else{
			echo json_encode($result);
		}
	});

	//Listar actividades by auditoria + fase
	$app->get('/lstActividadesByAuditoriaFase/:audi/:fase', function($audi, $fase)  use($app, $db) {
		$sql="SELECT idActividad id, concat(idFase, '.- ', descripcion) texto FROM sia_auditoriasactividades Where idAuditoria=:audi and idFase=:fase order by 2 asc";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':audi' => $audi, ':fase' => $fase));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON ACTIVIDADES. ");
		}else{
			echo json_encode($result);
		}
	});



	//Listar auditorias by sujeto + objeto
	$app->get('/lstAuditoriasBySujetoObjeto/:suj/:obj', function($suj, $obj)  use($app, $db) {
		$cuenta = $_SESSION["idCuentaActual"];


		$sql="SELECT idAuditoria id, concat(idAuditoria, ' ', tipoAuditoria) texto FROM sia_auditorias Where idCuenta=:cuenta and ltrim(idSujeto)=:suj and ltrim(idObjeto)=:obj order by 2 asc";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':cuenta' => $cuenta, ':suj' => $suj, ':obj' => $obj));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON ACTIVIDADES. ");
		}else{
			echo json_encode($result);
		}
	});




	$app->get('/notificaciones', function()  use ($app) {
		$app->render('notificaciones.php');
	});


	///////////////////////////////////////////////////////////////////////////////////////////









	$app->get('/perfil', function()    use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];
		$sql="SELECT idUsuario, nombre, paterno, materno, telefono, usuario FROM sia_usuarios WHERE idUsuario=:id";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $usrActual));
		$result['datos'] = $dbQuery->fetch(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			$app->render('perfil.php', $result);
		}
	})->name('listaPerfiles');

//Guardar SupervisorApoyo
	$app->post('/guardar/perfil', function()  use($app, $db) {

		$request=$app->request;
		$id = $request->post('txtID');
		$nombre = $request->post('txtNombre');
		$paterno = $request->post('txtPaterno');
		$materno = $request->post('txtMaterno');
		$telefono = $request->post('txtTelefono');
		$correo = $request->post('txtCorreo');

		$campana = $request->post('txtCampana');


		$nueva = $request->post('txtContrasenaNueva');
		$cambiar = $request->post('txtCambiarPass');

		$sNuevaContrasena="";
		if($cambiar=="SI") $sNuevaContrasena =", pwd=:pass ";

		try{
			$sql = "UPDATE sia_usuarios SET nombre=:nombre, paterno=:paterno, materno=:materno, telefono=:telefono, usuario=:correo " . $sNuevaContrasena . " Where idUsuario=:usrActual ";
			$dbQuery = $db->prepare($sql);


			//Actualizar contrasena
			if($cambiar=="SI") {
				$dbQuery->execute(array(':nombre'=> $nombre,':paterno'=> $paterno, ':materno'=> $materno, ':telefono'=> $telefono, ':correo'=> $correo, ':pass'=> $nueva, ':usrActual'=> $id));
			}else{
				$dbQuery->execute(array(':nombre'=> $nombre,':paterno'=> $paterno, ':materno'=> $materno, ':telefono'=> $telefono, ':correo'=> $correo, ':usrActual'=> $id));
			}

			//Actualizar la campana
			$sql = "UPDATE sia_cuentausuario SET predeterminada='NO' Where idUsuario=:usrActual";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':usrActual'=> $id));

			$sql = "UPDATE sia_cuentausuario SET predeterminada='SI' Where idCuenta=:campana and idUsuario=:usrActual ";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':campana'=> $campana, ':usrActual'=> $id));


			$result= array('idUsuario' => $_SESSION["idUsuario"] , 'nombre' => $_SESSION["sUsuario"] );
			$app->render('./dashboard.php', $result);



		}catch (PDOException $e) {
			print "¡Error!: " . $e->getMessage() . "<br/>";
			die();
		}
	});

	$app->get('/configuracion', function()  use ($app) {
		$app->render('configuracion.php');
	});

	$app->get('/reportes', function()  use ($app, $db) {
		$usrActual = $_SESSION["idUsuario"];
		$sql="SELECT r.idReporte, r.nombre sReporte, r.idModulo sModulo, r.archivo ".
		"FROM sia_usuarios u ".
		"INNER JOIN sia_usuariosRoles ur on u.idUsuario=ur.idUsuario ".
		"INNER JOIN sia_rolesReportes rr on ur.idRol=rr.idRol ".
		"INNER JOIN sia_reportes r on rr.idReporte=r.idReporte ".
		"WHERE u.idUsuario=:usrActual " .
		"ORDER BY r.nombre";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':usrActual' => $usrActual));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			//echo "Antes de REPORTES";
			$app->render('reportes.php', $result);
		}

	});



	//Parametros del reporte
	$app->get('/reporteParametros/:id', function($id)    use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];
		$sql="SELECT idParametro, tipo, etiqueta, globo, ancho, dominio, consulta, predeterminado FROM sia_reportesParametros WHERE idReporte=:id ORDER BY idParametro ";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});


	//Parametros del reporte
	$app->get('/expandirListaParametro/:idParametro', function($idParametro)    use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];

		$sql="SELECT consulta FROM sia_reportesParametros WHERE idParametro=:idParametro";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':idParametro' => $idParametro));
		$result = $dbQuery->fetch(PDO::FETCH_ASSOC);
		$sql = $result['consulta'];

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute();
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}

	});


	//Listar lstCampanas by Usr
	$app->get('/lstCuentasByUsr/:id',  function($id=0)  use($app, $db) {
		$id = (int)$id;
		try{
			$sql = "SELECT c.idCuenta id, c.nombre texto ".
			"FROM sia_cuentas c INNER JOIN sia_cuentausuario cu on c.idCuenta=cu.idCuenta ".
			"WHERE cu.idUsuario= :id ".
			"ORDER BY c.idCuenta";

			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':id' => $id));
			$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);

			if(!$result){
				$app->halt(404, "NO SE ENCONTRARON DATOS ");
			}else{
				echo json_encode($result);
			}
		}catch (PDOException $e) {
			print "¡Error!: " . $e->getMessage() . "<br/>";
			die();
		}
	});

//Lista de Módulos by Usuario
	$app->get('/lstModulosByUsuarioCampana/:id', function($id)    use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];
		$sql="SELECT m.idModulo, m.nombre, m.icono, m.panel, m.liga " .
		"FROM sia_rolesModulos rm " .
		"INNER JOIN sia_modulos m ON rm.idModulo=m.idModulo " .
		"WHERE rm.idRol in (Select idRol from sia_usuariosRoles Where idUsuario=:usrActual) " .
		"ORDER BY m.panel, m.orden ";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':usrActual' => $usrActual));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});





	$app->run();
