<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ProductosController;
use App\Http\Controllers\FoliosController;
use App\Http\Controllers\FacturasController;
use App\Http\Controllers\ComplementosController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\CommercialController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\Commercial\CommercialClientController;
use App\Http\Controllers\Commercial\CommercialContactController;
use App\Http\Controllers\Commercial\CommercialDocumentTemplateController;
use App\Http\Controllers\Commercial\CommercialQuoteController;

use App\Http\Controllers\Api\SeriesController;
use App\Http\Controllers\Api\ProductosApiController;
use App\Http\Controllers\Api\SatController;

/*
|--------------------------------------------------------------------------
| Web Routes (FactuCare - limpio)
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/dashboard');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    // 1) Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('comercial')->name('comercial.')->group(function () {
        Route::get('clientes', [CommercialClientController::class, 'index'])->name('clientes.index');
        Route::get('clientes/search/fiscales', [CommercialClientController::class, 'searchFiscalClients'])->name('clientes.search-fiscales');
        Route::get('search/clientes', [ClientesController::class, 'searchCommercialClients'])->name('search-clientes');
        Route::get('clientes/crear', [CommercialClientController::class, 'create'])->name('clientes.create');
        Route::post('clientes', [CommercialClientController::class, 'store'])->name('clientes.store');
        Route::get('clientes/{commercialClient}', [CommercialClientController::class, 'show'])->name('clientes.show');
        Route::get('clientes/{commercialClient}/editar', [CommercialClientController::class, 'edit'])->name('clientes.edit');
        Route::match(['put', 'patch'], 'clientes/{commercialClient}', [CommercialClientController::class, 'update'])->name('clientes.update');
        Route::delete('clientes/{commercialClient}', [CommercialClientController::class, 'destroy'])->name('clientes.destroy');

        Route::get('contactos', [CommercialContactController::class, 'index'])->name('contactos.index');
        Route::post('clientes/{commercialClient}/contactos', [CommercialContactController::class, 'store'])->name('contactos.store');
        Route::get('clientes/{commercialClient}/contactos/{commercialContact}/editar', [CommercialContactController::class, 'edit'])->name('contactos.edit');
        Route::match(['put', 'patch'], 'clientes/{commercialClient}/contactos/{commercialContact}', [CommercialContactController::class, 'update'])->name('contactos.update');
        Route::delete('clientes/{commercialClient}/contactos/{commercialContact}', [CommercialContactController::class, 'destroy'])->name('contactos.destroy');

        Route::get('cotizaciones/search/productos', [CommercialQuoteController::class, 'searchProducts'])->name('cotizaciones.search-productos');
        Route::get('cotizaciones/clientes/{commercialClient}/opciones', [CommercialQuoteController::class, 'clientOptions'])->name('cotizaciones.client-options');
        Route::get('cotizaciones', [CommercialQuoteController::class, 'index'])->name('cotizaciones.index');
        Route::get('cotizaciones/crear', [CommercialQuoteController::class, 'create'])->name('cotizaciones.create');
        Route::post('cotizaciones', [CommercialQuoteController::class, 'store'])->name('cotizaciones.store');
        Route::post('cotizaciones/previsualizar', [CommercialQuoteController::class, 'previewDraft'])->name('cotizaciones.preview-draft');
        Route::get('cotizaciones/{commercialQuote}', [CommercialQuoteController::class, 'show'])->whereNumber('commercialQuote')->name('cotizaciones.show');
        Route::get('cotizaciones/{commercialQuote}/editar', [CommercialQuoteController::class, 'edit'])->whereNumber('commercialQuote')->name('cotizaciones.edit');
        Route::match(['put', 'patch'], 'cotizaciones/{commercialQuote}', [CommercialQuoteController::class, 'update'])->whereNumber('commercialQuote')->name('cotizaciones.update');
        Route::post('cotizaciones/{commercialQuote}/enviar', [CommercialQuoteController::class, 'send'])->whereNumber('commercialQuote')->name('cotizaciones.send');
        Route::post('cotizaciones/{commercialQuote}/aceptar', [CommercialQuoteController::class, 'accept'])->whereNumber('commercialQuote')->name('cotizaciones.accept');
        Route::post('cotizaciones/{commercialQuote}/rechazar', [CommercialQuoteController::class, 'reject'])->whereNumber('commercialQuote')->name('cotizaciones.reject');
        Route::post('cotizaciones/{commercialQuote}/cancelar', [CommercialQuoteController::class, 'cancel'])->whereNumber('commercialQuote')->name('cotizaciones.cancel');
        Route::get('cotizaciones/{commercialQuote}/previsualizar', [CommercialQuoteController::class, 'preview'])->whereNumber('commercialQuote')->name('cotizaciones.preview');
        Route::get('cotizaciones/{commercialQuote}/pdf', [CommercialQuoteController::class, 'pdf'])->whereNumber('commercialQuote')->name('cotizaciones.pdf');
        Route::get('cotizaciones/{commercialQuote}/imprimir', [CommercialQuoteController::class, 'print'])->whereNumber('commercialQuote')->name('cotizaciones.print');
        Route::get('remisiones', [CommercialController::class, 'remisiones'])->name('remisiones');
        Route::get('cuentas-por-cobrar', [CommercialController::class, 'cuentasCobrar'])->name('cuentas-cobrar');
        Route::get('pagos-operativos', [CommercialController::class, 'pagosOperativos'])->name('pagos-operativos');
    });

    Route::prefix('operacion')->name('operacion.')->group(function () {
        Route::get('actividades', [OperationsController::class, 'actividades'])->name('actividades');
        Route::get('calendario', [OperationsController::class, 'calendario'])->name('calendario');
        Route::get('tareas', [OperationsController::class, 'tareas'])->name('tareas');
        Route::get('proyectos', [OperationsController::class, 'proyectos'])->name('proyectos');
    });

    // 2) Catálogos
    Route::prefix('catalogos')->group(function () {

        Route::resource('clientes', ClientesController::class)->except(['show']);
        Route::resource('productos', ProductosController::class)->except(['show']);
        Route::resource('folios', FoliosController::class)->except(['show']);

        // Empleados (placeholder por ahora)
        Route::view('empleados', 'pages/coming-soon')->name('empleados.index');

        // Drawer “Editar cliente” (si lo usas en facturas)
        Route::post('clientes/{cliente}', [ClientesController::class, 'updateJson'])
            ->name('clientes.updateJson');

        // SAT buscadores (si los usas en productos)
        Route::get('search/prodserv', [ProductosController::class, 'searchClaveProdServ'])->name('catalogos.search.prodserv');
        Route::get('search/unidades', [ProductosController::class, 'searchClaveUnidad'])->name('catalogos.search.unidades');
    });

    // 3) Facturas / Documentos
    Route::prefix('documentos')->group(function () {

        Route::get('facturas', [FacturasController::class, 'index'])->name('facturas.index');
        Route::get('facturas/nueva', [FacturasController::class, 'nueva'])->name('facturas.nueva');
        Route::get('facturas/create', [FacturasController::class, 'create'])->name('facturas.create');
        Route::post('facturas/preview', [FacturasController::class, 'preview'])->name('facturas.preview');
        Route::get('facturas/preview', [FacturasController::class, 'previewGet'])->name('facturas.preview.get');
        Route::post('facturas/timbrar', [FacturasController::class, 'timbrar'])->name('facturas.timbrar');
        //--nuevas rutas
        Route::get('/facturas/{id}/xml', [FacturasController::class, 'downloadXml'])->name('facturas.xml');
        Route::get('/facturas/{id}/pdf', [FacturasController::class, 'downloadPdf'])->name('facturas.pdf');
        Route::get('/facturas/{id}/ver', [FacturasController::class, 'show'])->name('facturas.ver');
        Route::get('/facturas/{id}/acuse', [FacturasController::class, 'downloadAcuse'])->name('facturas.acuse');
        Route::post('/facturas/{id}/regenerar-pdf', [FacturasController::class, 'regenerarPdf'])->name('facturas.regenerarPdf');

        Route::post('/facturas/{id}/cancelar', [FacturasController::class, 'cancelar'])->name('facturas.cancelar');


        Route::get('/facturas/chunk', [FacturasController::class, 'indexChunk'])->name('facturas.indexChunk');


        // Complementos de pago
        Route::get('complementos', [ComplementosController::class, 'index'])->name('complementos.index');
        //Route::get('complementos/nueva', [ComplementosController::class, 'nueva'])->name('complementos.nueva');
        
        Route::get('complementos/create', [ComplementosController::class, 'create'])->name('complementos.create');
        Route::post('complementos/preview', [ComplementosController::class, 'preview'])->name('complementos.preview');
        Route::post('complementos/timbrar', [ComplementosController::class, 'timbrar'])->name('complementos.timbrar');
        Route::get('complementos/{id}/ver', [ComplementosController::class, 'ver'])->name('complementos.ver');
        Route::get('complementos/{id}/xml', [ComplementosController::class, 'downloadXml'])->name('complementos.xml');
        Route::get('complementos/{id}/pdf', [ComplementosController::class, 'downloadPdf'])->name('complementos.pdf');
        Route::post('complementos/{id}/regenerar-pdf', [ComplementosController::class, 'regenerarPdf'])->name('complementos.regenerarPdf');
        Route::post('complementos/{id}/cancelar', [ComplementosController::class, 'cancelar'])->name('complementos.cancelar');
        // AJAX: facturas con saldo insoluto por cliente
        Route::get('complementos/facturas-pendientes', [ComplementosController::class, 'facturasPendientes'])
            ->name('complementos.facturasPendientes');


        Route::view('nominas', 'pages/coming-soon')->name('nominas.index');


    });

    Route::middleware(['web','auth'])->prefix('api')->group(function () {
        Route::get('series/next', [SeriesController::class, 'next'])->name('api.series.next');
        Route::get('productos/buscar', [ProductosApiController::class, 'buscar'])->name('api.productos.buscar');
        Route::get('sat/clave-prod-serv', [App\Http\Controllers\Api\SatController::class, 'prodServ']);
        Route::get('sat/clave-unidad', [App\Http\Controllers\Api\SatController::class, 'unidad']);
    });


    // 4) Reportes
    Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/excel', [ReportesController::class, 'exportExcel'])->name('reportes.excel');
    Route::get('/reportes/pdf', [ReportesController::class, 'exportPdf'])->name('reportes.pdf');

    // 5) Configuración
    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
    Route::get('/configuracion/formatos-documentos', [CommercialDocumentTemplateController::class, 'index'])->name('configuracion.formatos-documentos.index');
    Route::get('/configuracion/formatos-documentos/crear', [CommercialDocumentTemplateController::class, 'create'])->name('configuracion.formatos-documentos.create');
    Route::post('/configuracion/formatos-documentos', [CommercialDocumentTemplateController::class, 'store'])->name('configuracion.formatos-documentos.store');
    Route::get('/configuracion/formatos-documentos/{template}/editar', [CommercialDocumentTemplateController::class, 'edit'])->name('configuracion.formatos-documentos.edit');
    Route::match(['put', 'patch'], '/configuracion/formatos-documentos/{template}', [CommercialDocumentTemplateController::class, 'update'])->name('configuracion.formatos-documentos.update');
    Route::post('/configuracion/formatos-documentos/{template}/predeterminado', [CommercialDocumentTemplateController::class, 'setDefault'])->name('configuracion.formatos-documentos.default');
    Route::delete('/configuracion/formatos-documentos/{template}', [CommercialDocumentTemplateController::class, 'destroy'])->name('configuracion.formatos-documentos.destroy');
    Route::post('/configuracion/perfil', [ConfiguracionController::class, 'updatePerfil'])->name('configuracion.perfil');
    Route::post('/configuracion/csd', [ConfiguracionController::class, 'uploadCsd'])->name('configuracion.csd');
    Route::delete('/configuracion/logo', [ConfiguracionController::class, 'destroyLogo'])->name('configuracion.logo.destroy');
    Route::delete('/configuracion/documentos/{id}', [ConfiguracionController::class, 'destroyDocumento'])->name('configuracion.documentos.destroy');

    Route::prefix('administracion')->name('administracion.')->group(function () {
        Route::get('usuarios', [AdministrationController::class, 'usuarios'])->name('usuarios');
        Route::get('roles-y-permisos', [AdministrationController::class, 'roles'])->name('roles');
        Route::get('configuracion-general', [AdministrationController::class, 'general'])->name('general');
    });

    // APIs (usan sesión, no tokens)
    Route::prefix('api')->group(function () {
        Route::get('series/next', [SeriesController::class, 'next']);
        Route::get('productos/buscar', [ProductosApiController::class, 'buscar']);
        Route::get('sat/clave-prod-serv', [SatController::class, 'prodServ']);
        Route::get('sat/clave-unidad', [SatController::class, 'unidad']);
    });

    // Fallback
    Route::fallback(function () {
        return view('pages/utility/404');
    });
});
