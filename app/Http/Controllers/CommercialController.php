<?php

namespace App\Http\Controllers;

class CommercialController extends Controller
{
    public function cotizaciones()
    {
        return $this->placeholder('cotizaciones');
    }

    public function remisiones()
    {
        return $this->placeholder('remisiones');
    }

    public function cuentasCobrar()
    {
        return $this->placeholder('cuentas_cobrar');
    }

    public function pagosOperativos()
    {
        return $this->placeholder('pagos_operativos');
    }

    private function placeholder(string $module)
    {
        $modules = [
            'cotizaciones' => [
                'title' => 'Cotizaciones',
                'description' => 'Espacio preparado para propuestas comerciales antes de convertirse en remisiones o facturas.',
                'objective' => 'Construir cotizaciones con partidas, vigencias, seguimiento y conversion futura.',
            ],
            'remisiones' => [
                'title' => 'Remisiones',
                'description' => 'Modulo previsto para controlar entregas y documentos operativos previos a facturacion.',
                'objective' => 'Registrar salidas, entregas y referencias comerciales sin afectar el modulo fiscal.',
            ],
            'cuentas_cobrar' => [
                'title' => 'Cuentas por cobrar',
                'description' => 'Vista futura para seguimiento de saldos comerciales y cobranza operativa.',
                'objective' => 'Concentrar vencimientos, saldos, promesas de pago y conciliacion comercial.',
            ],
            'pagos_operativos' => [
                'title' => 'Pagos operativos',
                'description' => 'Modulo preparado para registrar pagos no fiscales y seguimiento administrativo.',
                'objective' => 'Controlar pagos operativos sin crear CFDI ni modificar complementos de pago.',
            ],
        ];

        return view('platform.placeholder', [
            'area' => 'Comercial',
            'title' => $modules[$module]['title'],
            'description' => $modules[$module]['description'],
            'objective' => $modules[$module]['objective'],
        ]);
    }
}
