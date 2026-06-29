<?php

namespace App\Services\CommercialDocuments;

class TemplateVariableResolver
{
    public function resolve(?string $text, array $context): string
    {
        $text = (string) ($text ?? '');
        if ($text === '') {
            return '';
        }

        return preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function (array $matches) use ($context) {
            $key = strtolower($matches[1]);
            if (!array_key_exists($key, $this->allowedVariables())) {
                return $matches[0];
            }

            return (string) data_get($context, $key, '');
        }, $text) ?? $text;
    }

    public function allowedVariables(): array
    {
        return [
            'empresa.nombre' => 'Nombre de la empresa',
            'empresa.rfc' => 'RFC de la empresa',
            'empresa.telefono' => 'Telefono de la empresa',
            'empresa.email' => 'Email de la empresa',
            'empresa.direccion' => 'Direccion de la empresa',
            'cliente.nombre' => 'Nombre del cliente',
            'cliente.nombre_comercial' => 'Nombre comercial',
            'cliente.email' => 'Email del cliente',
            'cliente.telefono' => 'Telefono del cliente',
            'cliente.direccion' => 'Direccion del cliente',
            'contacto.nombre' => 'Nombre del contacto',
            'contacto.puesto' => 'Puesto del contacto',
            'contacto.email' => 'Email del contacto',
            'contacto.telefono' => 'Telefono del contacto',
            'cotizacion.folio' => 'Folio de cotizacion',
            'cotizacion.fecha' => 'Fecha de emision',
            'cotizacion.vencimiento' => 'Fecha de vencimiento',
            'cotizacion.moneda' => 'Moneda',
            'cotizacion.subtotal' => 'Subtotal',
            'cotizacion.descuento' => 'Descuento',
            'cotizacion.impuestos' => 'Impuestos',
            'cotizacion.total' => 'Total',
            'cotizacion.responsable' => 'Usuario responsable',
        ];
    }
}
