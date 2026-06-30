# QA manual: drawer de impuestos en cotizaciones

## Cancelar descarta cambios

1. Abrir `/comercial/cotizaciones/crear`.
2. Agregar una partida.
3. Abrir `Impuestos`.
4. Agregar `IVA` traslado con tasa `0.160000`.
5. Presionar `Cancelar`.
6. Verificar que el badge de la partida siga en `0`, el resumen diga `Sin impuestos` y no existan inputs `items[n][taxes][m]` en el formulario.

## Aplicar conserva cambios

1. Abrir `Impuestos` en la misma partida.
2. Agregar `IVA` traslado con tasa `0.160000`.
3. Presionar `Aplicar impuestos`.
4. Verificar que aparezca el mensaje `Impuestos aplicados a la partida`.
5. Verificar badge `1`, resumen `IVA 16%` y payload `items[n][taxes][0][tax_name]`.

## Reabrir carga lo aplicado

1. Reabrir el drawer de la partida.
2. Confirmar que `IVA` aparece cargado.
3. Agregar `ISR` retencion con tasa `0.100000`.
4. Presionar `Aplicar impuestos`.
5. Verificar badge `2` y resumen con ambos impuestos.

## Cierres sin aplicar

1. Reabrir el drawer.
2. Cambiar temporalmente `IVA` a otro nombre.
3. Cerrar con `X`.
4. Reabrir y confirmar que el cambio no aplicado no se guardo.
5. Repetir con clic en backdrop y con tecla Escape.

## Persistencia

1. Aplicar al menos dos impuestos.
2. Guardar cotizacion.
3. Verificar en base de datos que existan registros separados en `commercial_quote_taxes`.
4. Confirmar que `commercial_quote_items.snapshot_tax_name` no se use como fuente nueva.
