Proyecto: iKontrol basado en FC2
Framework: Laravel 11 / PHP 8.2 / MySQL
Servidor: ikontrol.solutions/sistema
Base desarrollo: tws001_testfc

NO TOCAR:
- app/Http/Controllers/FacturasController.php
- app/Http/Controllers/ComplementosController.php
- app/Http/Controllers/Traits/PacMultiPacTrait.php
- app/Extensions/MultiPac/MultiPac.php
- lógica CFDI, XML, PAC, timbrado, cancelación y complementos

Módulos actuales:
- Clientes fiscales: App\Models\Cliente, tabla clientes, dueño users_id
- Productos fiscales: App\Models\Producto, tabla productos
- Clientes comerciales: CommercialClient, commercial_clients
- Contactos comerciales: CommercialContact, commercial_contacts
- Relación comercial-fiscal: commercial_client_fiscal_client
- Cotizaciones: CommercialQuote, commercial_quotes
- Formatos comerciales: CommercialDocumentTemplate, commercial_document_templates

Rutas comerciales:
- comercial.clientes.*
- comercial.contactos.*
- comercial.cotizaciones.*

Reglas:
- No usar float/double para dinero.
- No migrate:fresh.
- Toda migración nueva debe usar índices/foreign keys con nombres cortos menores de 64 caracteres.
- Primero revisar route:list y php artisan test antes de declarar terminado.