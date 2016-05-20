<?php

	// Inicio nuevo Código HVS 20160511 06:30
	//CODIGO PHP

	$app->get('/catInhabiles', function()  use ($app, $db) {
		//$cuenta = $_SESSION["idCuentaActual"];

		$sql="SELECT dh.idCuenta idCta, dh.idDia idDia, dh.tipo tipo, dh.nombre nombre, CONVERT(VARCHAR(12),dh.fInicio,102) fInicio, CONVERT(VARCHAR(12),dh.fFin,102) fFin, dh.estatus estatus FROM sia_diasinhabiles dh ORDER BY  dh.idCuenta, dh.idDia DESC";
		$dbQuery = $db->prepare($sql);
//		$dbQuery->execute(array(':cuenta' => $cuenta));
		$dbQuery->execute();
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS.");
		}else{
			$app->render('catInhabiles.php', $result);
		}
	})->name('listaInhabiles');

	// Fin nuevo Código HVS 20160511 06:30


	/* **********************************************************************************
		INICIA CODIGO HVS 2016/05/17
	 ***********************************************************************************
	*/
	// Obten los registro que cumplan con el día inhábil
	$app->get('/lstInhabilByID/:id', function($id)    use($app, $db) {
		$sql="SELECT idCuenta idDia, tipo, nombre, fInicio, fFin, usrAlta, fAlta, estatus " .
		"FROM sia_diasinhabiles WHERE idDia=:id ";
		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result = $dbQuery->fetch(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON DATOS ");
		}else{
			echo json_encode($result);
		}
	});

	// Obten los registro que cumplan con el tipo de auditoría enviado
	$app->get('/lstCriteriosByTipoAuditoria/:id', function($id)  use($app, $db) {

		$sql="SELECT idCriterio id, nombre texto FROM sia_criterios Where idTipoAuditoria=:id";

		$dbQuery = $db->prepare($sql);
		$dbQuery->execute(array(':id' => $id));
		$result['datos'] = $dbQuery->fetchAll(PDO::FETCH_ASSOC);
		if(!$result){
			$app->halt(404, "NO SE ENCONTRARON CRITERIOS PARA MOSTRAR. ");
		}else{
			echo json_encode($result);
		}
	});

	/* **********************************************************************************
		FINALIZA CODIGO HVS 2016/05/17
	 ***********************************************************************************
	*/


	/* **********************************************************************************
		INICIA CODIGO HVS 
	 ***********************************************************************************
	*/
		//Guarda un día inhábil
	$app->post('/guardar/inhabiles', function()  use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];
		$cuenta = $_SESSION["idCuentaActual"];

		$request=$app->request;

		//$cuenta = $request->post('txtCuenta');
		$dia = $request->post('txtDia');
		$tipo = $request->post('txtTipo');
		$nombre = strtoupper($request->post('txtNombre'));

		//$fInicio = $request->post('txtFechaInicial');
		$fInicio = date_create(($request->post('txtFechaInicial')));
		$fInicio = $fInicio->format('Y-m-d');

		//$fFin = $request->post('txtFechaFinal');
		$fFin = date_create(($request->post('txtFechaFinal')));
		$fFin = $fFin->format('Y-m-d');

		$estatus = $request->post('txtEstatus');

		$oper = $request->post('txtOperacion');

		//echo nl2br("\nEl valor de Oper es: ".$oper);
		//echo nl2br("\nValor usrActual ".$usrActual);
		//echo nl2br("\nValor cuenta ".$cuenta);
		//echo nl2br("\nValor dia ".$dia);
		//echo nl2br("\nValor tipo ".$tipo);
		//echo nl2br("\nValor nombre ".$nombre);
		//echo nl2br("\nValor fInicio ".$fInicio);
		//echo nl2br("\nValor fFin ".$fFin);
		//echo nl2br("\nValor estatus ".$estatus);

		try
		{
			if($oper=='INS')
			{
				$sql="INSERT INTO sia_diasinhabiles (idCuenta, tipo, nombre, fInicio, fFin, usrAlta, fAlta, estatus) " .
				"VALUES(:cuenta, :tipo, :nombre, :fInicio, :fFin, :usrActual, getdate(), 'ACTIVO');";
				$dbQuery = $db->prepare($sql);

				$dbQuery->execute(array(':cuenta' => $cuenta, ':tipo' => $tipo, ':nombre' => $nombre, ':fInicio' => $fInicio, ':fFin' => $fFin, ':usrActual' => $usrActual ));
				//echo "<br>INS OK<hr>";

			}else{

				$sql="UPDATE sia_diasinhabiles SET " .
				"idCuenta=:cuenta, tipo=:tipo, nombre=:nombre, fInicio=:fInicio, fFin=:fFin, usrModificacion=:usrActual, " .
				" fModificacion=getdate(), estatus=:estatus WHERE idDia =:dia";
				$dbQuery = $db->prepare($sql);

				$dbQuery->execute(array(':cuenta' => $cuenta, ':tipo' => $tipo, ':nombre' => $nombre, ':fInicio' => $fInicio, ':fFin' => $fFin, ':usrActual' => $usrActual, ':estatus' => $estatus, ':dia' => $dia));
				//echo "<br>UPD OK";

			}

			//echo nl2br("\nQuery Ejecutado : ".$sql);

		}catch (Exception $e) {
			print "¡Error!: " . $e->getMessage() . "<br/>";
			die();
		}
		$app->redirect($app->urlFor('listaInhabiles'));
	});

	//Guarda un nuevo criterio para la auditoria
	$app->post('/guardar/auditoriaCriterios', function()  use($app, $db) {
		$usrActual = $_SESSION["idUsuario"];
		$cuenta = $_SESSION["idCuentaActual"];

		$request=$app->request;
		/*
		$programa = $request->post('txtPrograma');
		$auditoria = $request->post('txtTipoAuditoria');
		$criterio = $request->post('txtCriterio');
		$justificacion = strtoupper($request->post('txtJustificacionCriterio'));
		$elementos = strtoupper($request->post('txtElementosCriterio'));
		$oper = $request->post('txtOperacion');
 
 		echo nl2br("\nEl valor de Oper es: ".$oper);
 		*/
		echo nl2br("\nValor usrActual:".$usrActual);
		echo nl2br("\nValor cuenta: ".$cuenta);
		/*
		echo nl2br("\nValor programa: ".$programa);
		echo nl2br("\nValor Auditoria: ".$Auditoria);
		echo nl2br("\nValor criterio: ".$criterio);
		echo nl2br("\nValor justificacion: ".$justificacion);
		echo nl2br("\nValor elementos: ".$elementos);
		//echo nl2br("\nValor estatus: ".$estatus);
  		*/
  		/*	
		try
		{
			if($oper=='INS')
			{
				$sql="INSERT INTO sia_auditoriascriterios ". 
				"(idCuenta, idPrograma, idAuditoria, idCriterio, justificacion, elementos, usrAlta, fAlta, estatus) " .
				"VALUES(:cuenta, :programa, :auditoria, :criterio, :justificacion, :elementos, :usrActual, getdate(), 'ACTIVO');";
				$dbQuery = $db->prepare($sql);

				$dbQuery->execute(array(':cuenta' => $cuenta, ':programa:' => $programa, ':auditoria' => $auditoria, ':criterio' => $criterio, ':Justificacion' => $justificacion, ':elementos' => $elementos, ':usrActual' => $usrActual ));
			}else{

				$sql="UPDATE sia_auditoriascriterios SET " . 
				"idCriterio=:criterio, justificacion=:justificacion, elementos=:elementos, " . 
				"usrModificacion=:usrActual, fModificacion=getdate() " . 
				"WHERE idCuenta=:cuenta and idPrograma=:programa and idAuditoria=:auditoria ";
				$dbQuery = $db->prepare($sql);

				$dbQuery->execute(array(':criterio' => $criterio, ':justificacion' => $justificacion, ':elementos' => $elementos, ':usrActual' => $usrActual, ':cuenta' => $cuenta, ':programa:' => $programa, ':auditoria' => $auditoria ));
			}

			echo nl2br("\nQuery Ejecutado : ".$sql);

		}catch (Exception $e) {
			print "¡Error!: " . $e->getMessage() . "<br/>";
			die();
		}
		*/
		//$app->redirect($app->urlFor('listaAuditoriaCriterios'));
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


	/* **********************************************************************************
		FINALIZA CODIGO HVS 
	 ***********************************************************************************
	*/

?>
