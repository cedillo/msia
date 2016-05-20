
	$app->get('/cargarArchivo/Universo/:nombre/:cuenta', function($nombre, $cuenta)    use($app, $db) {	
		try{
			$usrActual = $_SESSION["idUsuario"];
			$archivo ='uploads/' . $nombre;

			//Elimina cuenta pública
			$sql="DELETE FROM sia_cuentasingresos WHERE idCuenta= :cuenta ;";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cuenta' => $cuenta));

			//Abrir archivo de XLS
			$xlsx = new SimpleXLSX($archivo);
			list($num_cols, $num_rows) = $xlsx->dimension();			

			//insertar 
			$sql="INSERT INTO sia_cuentasuniversos (idCuenta, origen, tipo, clave, nivel, nombre,  original, recaudado, usrAlta, fAlta, estatus) " .
			"values(:cuenta,:origen, :tipo, :clave, :nivel, :nombre, :original, :recaudado, :usrActual, getdate(), 'ACTIVO')";

			$dbQuery = $db->prepare($sql);
			$nRegistros=0;
			
			error_reporting(E_ALL ^ E_NOTICE);
			
			foreach( $xlsx->rows() as $row ) {				
				$origen = $row[0];
				$tipo =  "" . $row[1];
				$clave =  "" . $row[2];
				$nivel =  "" . $row[3];
				$nombre =  "" . $row[4];
				$original =  "" . $row[5];
				$recaudado =  "" . $row[6];
								
				if ($nRegistros>0){
					//$dbQuery->execute(array(':cuenta' => $cuenta, ':origen' => $origen, ':tipo' => $tipo,':clave' => $clave, ':nivel' => $nivel, ':nombre' => $nombre, 
					//':original' => $original, ':recaudado' => $recaudado,':usrActual' => $usrActual));								
				}				
				$nRegistros++;	
			}
			echo "Se cargaron " . $nRegistros . " registro(s).";
		}catch (PDOException $e) {
			echo  "<br>¡Error en el TRY!: " . $e->getMessage();
			die();
		}
	});	

	
	$app->get('/cargarArchivo/Ingreso/:nombre/:cuenta', function($nombre, $cuenta)    use($app, $db) {	
		try{
			$usrActual = $_SESSION["idUsuario"];
			$archivo ='uploads/' . $nombre;

			//Elimina cuenta pública
			$sql="DELETE FROM sia_cuentasingresos WHERE idCuenta= :cuenta ;";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cuenta' => $cuenta));

			//Abrir archivo de XLS
			$xlsx = new SimpleXLSX($archivo);
			list($num_cols, $num_rows) = $xlsx->dimension();			

			//insertar 
			$sql="INSERT INTO sia_cuentasingresos (idCuenta, origen, tipo, clave, nivel, nombre,  original, recaudado, usrAlta, fAlta, estatus) " .
			"values(:cuenta,:origen, :tipo, :clave, :nivel, :nombre, :original, :recaudado, :usrActual, getdate(), 'ACTIVO')";

			$dbQuery = $db->prepare($sql);
			$nRegistros=0;
			
			error_reporting(E_ALL ^ E_NOTICE);
			
			foreach( $xlsx->rows() as $row ) {				
				$origen = $row[0];
				$tipo =  "" . $row[1];
				$clave =  "" . $row[2];
				$nivel =  "" . $row[3];
				$nombre =  "" . $row[4];
				$original =  "" . $row[5];
				$recaudado =  "" . $row[6];
								
				if ($nRegistros>0){
					$dbQuery->execute(array(':cuenta' => $cuenta, ':origen' => $origen, ':tipo' => $tipo,':clave' => $clave, ':nivel' => $nivel, ':nombre' => $nombre, 
					':original' => $original, ':recaudado' => $recaudado,':usrActual' => $usrActual));								
				}				
				$nRegistros++;	
			}
			echo "Se cargaron " . $nRegistros . " registro(s).";
		}catch (PDOException $e) {
			echo  "<br>¡Error en el TRY!: " . $e->getMessage();
			die();
		}
	});

	$app->get('/cargarArchivo/Egreso/:nombre/:cuenta', function($nombre, $cuenta)    use($app, $db) {	
		try{
			$usrActual = $_SESSION["idUsuario"];
			$archivo ='uploads/' . $nombre;

			//Elimina cuenta pública
			$sql="DELETE FROM sia_cuentasdetalles WHERE idCuenta= :cuenta ;";
			$dbQuery = $db->prepare($sql);
			$dbQuery->execute(array(':cuenta' => $cuenta));

			//Abrir archivo de XLS
			$xlsx = new SimpleXLSX($archivo);
			list($num_cols, $num_rows) = $xlsx->dimension();			

			//insertar 
			$sql="INSERT INTO sia_cuentasdetalles " .
			"(idCuenta, sector, subsector, unidad, funcion, subfuncion, actividad, capitulo, partida, finalidad, progPres, fuenteFinanciamiento, fuenteGenerica, fuenteEspecifica, " .
			"origenRecurso, tipoGasto, digito, proyecto, destinoGasto, original, modificado, ejercido, pagado, pendiente, usrAlta, fAlta, estatus) " .
			"values(:cuenta,:sector, :subsector, :unidad, :funcion, :subfuncion, :actividad, :capitulo, :partida, :finalidad, :progPres, :fuenteFinanciamiento, :fuenteGenerica, :fuenteEspecifica, " .
			":origenRecurso, :tipoGasto, :digito, :proyecto, :destinoGasto, :original, :modificado, :ejercido, :pagado, :pendiente, :usrActual, getdate(), 'ACTIVO');";

			$dbQuery = $db->prepare($sql);
			$nRegistros=0;
			
			error_reporting(E_ALL ^ E_NOTICE);
			
			foreach( $xlsx->rows() as $row ) {				
				$sector = $row[0];
				$subsector =  "" . $row[1];
				$unidad =  "" . $row[2];
				$funcion =  "" . $row[3];
				$subfuncion =  "" . $row[4];
				$actividad =  "" . $row[5];
				$capitulo =  "" . $row[6];
				$partida =  "" . $row[7];
				$finalidad =  "" . $row[8];
				$progPres =  "" . $row[9];
				$fuenteFinanciamiento =  "" . $row[10];
				$fuenteGenerica =  "" . $row[11];
				$fuenteEspecifica =  "" . $row[12];
				$origenRecurso =  "" . $row[13];
				$tipoGasto =  "" . $row[14];
				$digito =  "" . $row[15];
				$proyecto =  "" . $row[16];
				$destinoGasto =  "" . $row[17];
				$original =  "" . $row[18];
				$modificado =  "" . $row[19];
				$ejercido =  "" . $row[20];
				$pagado =  "" . $row[21];
				$pendiente =  "" . $row[22];	
								
				if ($nRegistros>0){
					$dbQuery->execute(array(':cuenta' => $cuenta, ':sector' => $sector, ':subsector' => $subsector,':unidad' => $unidad, ':funcion' => $funcion, ':subfuncion' => $subfuncion, ':actividad' => $actividad,
					':capitulo' => $capitulo, ':partida' => $partida, ':finalidad' => $finalidad, ':progPres' => $progPres, ':fuenteFinanciamiento' => $fuenteFinanciamiento, ':fuenteGenerica' => $fuenteGenerica,
					':fuenteEspecifica' => $fuenteEspecifica, ':origenRecurso' => $origenRecurso, ':tipoGasto' => $tipoGasto, ':digito' => $digito, ':proyecto' => $proyecto, ':destinoGasto' => $destinoGasto,
					':original' => $original, ':modificado' => $modificado, ':ejercido' => $ejercido, ':pagado' => $pagado, ':pendiente' => $pendiente, ':usrActual' => $usrActual));								
				}				
				$nRegistros++;	
			}
			echo "Se cargaron " . $nRegistros . " registro(s).";
		}catch (PDOException $e) {
			echo  "<br>¡Error en el TRY!: " . $e->getMessage();
			die();
		}
	});

