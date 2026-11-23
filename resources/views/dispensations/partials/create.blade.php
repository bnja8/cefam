    @if ($screen === 'create')
    <section class="card" id="registrar-entrega">
        <div class="card-header"><h2 class="card-title"><i class="fa-solid fa-barcode text-primary"></i> Registrar dispensación</h2></div>
        <div class="card-body">
            <form id="dispensation-form" action="{{ route('dispensations.store') }}" method="post" class="stack-form" data-dispensation-form>
                @csrf
                <input type="hidden" name="transcripcion_manual" value="1">
                <input type="hidden" name="tratamiento_sos" value="{{ old('tratamiento_sos', '0') ? '1' : '0' }}" data-treatment-sos-mode>
                <div class="form-grid">
                    <x-patient-search
                        id="patient-search"
                        value-id="paciente_rut"
                        :selected-patient="$selectedPatient"
                        :options-url="route('dispensations.patient-options', ['patientRut' => '__PATIENT__'])"
                        required
                    />
                    <div class="form-group">
                        <label for="receta_id" data-recipe-select-label>Receta vigente *</label>
                        <select class="form-control" id="receta_id" name="receta_id" data-selected-recipe="{{ old('receta_id') }}" required disabled><option value="">Selecciona primero un paciente</option></select>
                        <small data-recipe-select-help>Al seleccionar una receta, se cargan sus medicamentos y lotes FEFO.</small>
                        @error('receta_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-span dispensation-authorized-person-picker">
                        <div class="form-group">
                            <label for="persona_autorizada_id">Persona que retira el medicamento</label>
                            <select class="form-control" id="persona_autorizada_id" name="persona_autorizada_id" disabled><option value="">No, retira el paciente</option></select>
                        </div>
                        <button class="btn btn-outline btn-small" type="button" data-toggle-authorized-person aria-controls="new-authorized-person" aria-expanded="{{ old('nueva_persona_autorizada') ? 'true' : 'false' }}" disabled>
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                            <span data-authorized-person-button-label>{{ old('nueva_persona_autorizada') ? 'Cerrar formulario' : 'Registrar persona autorizada' }}</span>
                        </button>
                    </div>
                </div>
                <section
                    class="dispensation-new-authorized-person"
                    id="new-authorized-person"
                    aria-labelledby="new-authorized-person-title"
                    data-new-authorized-person
                    data-open-on-load="{{ old('nueva_persona_autorizada') ? 'true' : 'false' }}"
                    @unless (old('nueva_persona_autorizada')) hidden @endunless
                >
                    <header class="dispensation-context-header">
                        <div>
                            <h3 id="new-authorized-person-title"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Registrar persona autorizada</h3>
                            <p>La autorización se asociará a este paciente y se guardará al confirmar la dispensación.</p>
                        </div>
                        <span class="dispensation-context-badge" data-authorized-person-patient>
                            Paciente vinculado{{ old('paciente_rut') ? ': '.old('paciente_rut') : '' }}
                        </span>
                    </header>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nueva-persona-autorizada-rut">RUT de la persona autorizada *</label>
                            <input class="form-control" id="nueva-persona-autorizada-rut" name="nueva_persona_autorizada[rut]" value="{{ old('nueva_persona_autorizada.rut') }}" maxlength="12" placeholder="12.345.678-9" data-rut-input data-new-authorized-person-field @required(old('nueva_persona_autorizada')) @disabled(! old('nueva_persona_autorizada'))>
                            @error('nueva_persona_autorizada.rut')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group">
                            <label for="nueva-persona-autorizada-nombre">Nombre *</label>
                            <input class="form-control" id="nueva-persona-autorizada-nombre" name="nueva_persona_autorizada[nombre]" value="{{ old('nueva_persona_autorizada.nombre') }}" maxlength="50" placeholder="Nombre" data-new-authorized-person-field @required(old('nueva_persona_autorizada')) @disabled(! old('nueva_persona_autorizada'))>
                            @error('nueva_persona_autorizada.nombre')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group">
                            <label for="nueva-persona-autorizada-apellido-paterno">Apellido paterno *</label>
                            <input class="form-control" id="nueva-persona-autorizada-apellido-paterno" name="nueva_persona_autorizada[apellidoPaterno]" value="{{ old('nueva_persona_autorizada.apellidoPaterno') }}" maxlength="50" placeholder="Apellido paterno" data-new-authorized-person-field @required(old('nueva_persona_autorizada')) @disabled(! old('nueva_persona_autorizada'))>
                            @error('nueva_persona_autorizada.apellidoPaterno')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group">
                            <label for="nueva-persona-autorizada-apellido-materno">Apellido materno *</label>
                            <input class="form-control" id="nueva-persona-autorizada-apellido-materno" name="nueva_persona_autorizada[apellidoMaterno]" value="{{ old('nueva_persona_autorizada.apellidoMaterno') }}" maxlength="50" placeholder="Apellido materno" data-new-authorized-person-field @required(old('nueva_persona_autorizada')) @disabled(! old('nueva_persona_autorizada'))>
                            @error('nueva_persona_autorizada.apellidoMaterno')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-group">
                            <label for="nueva-persona-autorizada-vinculo">Vínculo con el paciente *</label>
                            <input class="form-control" id="nueva-persona-autorizada-vinculo" name="nueva_persona_autorizada[vinculo]" value="{{ old('nueva_persona_autorizada.vinculo') }}" maxlength="100" placeholder="Hija, cuidador/a, apoderado" data-new-authorized-person-field @required(old('nueva_persona_autorizada')) @disabled(! old('nueva_persona_autorizada'))>
                            @error('nueva_persona_autorizada.vinculo')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                    </div>
                </section>
                <section class="dispensation-prescription-check" aria-labelledby="prescription-check-title" data-prescription-verification hidden>
                    <header class="dispensation-context-header">
                        <div>
                            <h3 id="prescription-check-title"><i class="fa-regular fa-file-lines" aria-hidden="true"></i> Verificación de la receta presentada</h3>
                            <p>Verifica los datos de la receta presentada antes de confirmar la dispensación.</p>
                        </div>
                        <span class="dispensation-context-badge" data-recipe-folio></span>
                    </header>
                    <div class="prescription-verification-grid">
                        <article class="prescription-verification-item">
                            <span>Médico prescriptor</span>
                            <strong data-recipe-prescriber></strong>
                            <small data-recipe-prescriber-rut></small>
                        </article>
                        <article class="prescription-verification-item">
                            <span>Vigencia de prescripción</span>
                            <strong data-recipe-emission></strong>
                            <small data-recipe-expiration></small>
                        </article>
                        <article class="prescription-verification-item">
                            <span>Programa de salud APS</span>
                            <strong data-recipe-program></strong>
                            <small>Programa de salud del paciente</small>
                        </article>
                        <article class="prescription-verification-item">
                            <span>Observación de la receta</span>
                            <strong data-recipe-observation></strong>
                        </article>
                    </div>
                    <div class="prescription-verification-medicines">
                        <h4 id="prescription-medicines-title">Fármacos prescritos</h4>
                        <div class="prescription-verification-table-wrap">
                            <table class="prescription-verification-table" aria-labelledby="prescription-medicines-title">
                                <thead>
                                    <tr>
                                        <th scope="col">Fármaco prescrito</th>
                                        <th scope="col">Dosis / toma</th>
                                        <th scope="col">Frecuencia</th>
                                        <th scope="col">Duración</th>
                                        <th scope="col">Cant. prescrita</th>
                                        <th scope="col">Indicaciones médicas</th>
                                    </tr>
                                </thead>
                                <tbody data-recipe-medicine-rows aria-live="polite"></tbody>
                            </table>
                        </div>
                    </div>
                    <label class="prescription-verification-confirm">
                        <input type="checkbox" name="confirmacion_receta" value="1" data-prescription-confirmation @checked(old('confirmacion_receta')) disabled>
                        <span>Confirmo que cotejé la información registrada con la receta presentada por el paciente o la persona autorizada.</span>
                    </label>
                    @error('confirmacion_receta')<small class="field-error">{{ $message }}</small>@enderror
                </section>
                <div class="detail-heading">
                    <h3 data-dispensation-mode-title>Medicamentos prescritos</h3>
                    <span class="dispensation-recipe-summary" data-dispensation-recipe-summary aria-live="polite"></span>
                    <button class="btn btn-outline btn-small" type="button" data-add-row="dispensation-details"><i class="fa-solid fa-plus"></i> Agregar medicamento</button>
                    <button class="btn btn-outline btn-small" type="button" data-toggle-sos-treatment aria-pressed="{{ old('tratamiento_sos') ? 'true' : 'false' }}">Tratamiento SOS</button>
                </div>
                <x-barcode-scanner-test />
                <div id="dispensation-details" class="detail-rows" data-next-index="{{ count($detailRows) }}" data-distinct-products-list data-dispensation-details>
                    @foreach ($detailRows as $index => $detail)
                        <div class="detail-row prescription-detail-row dispensation-prescription-row" data-dispensation-row data-prescription-row>
                            <header class="dispensation-prescription-heading" data-dispensation-prescription-heading hidden>
                                <strong data-dispensation-product-name></strong>
                                <div class="dispensation-fefo-summary" data-prescription-fefo role="status" aria-live="polite" hidden></div>
                            </header>
                            <x-product-search
                                id="dispensation-product-{{ $index }}"
                                name="detalles[{{ $index }}][producto_id]"
                                :product="$selectedProducts->get((int) ($detail['producto_id'] ?? 0))"
                                barcode-url="{{ route('products.barcode.lookup') }}"
                                fefo-url="{{ route('products.api.fefo', 0) }}"
                                field-class="prescription-field-product"
                                search-label="Medicamento *"
                                search-help="Busca el medicamento indicado en la receta y selecciona un resultado."
                                :prescription="true"
                                :show-availability="false"
                            />
                            <div class="form-group prescription-field-dose" data-prescription-only><label>Dosis por toma *</label><input class="form-control" name="detalles[{{ $index }}][unidades_por_toma]" type="number" min="0.01" step="0.01" value="{{ $detail['unidades_por_toma'] ?? '' }}" required data-dose-amount><small>Cantidad administrada en cada toma; la frecuencia indica el intervalo entre dosis.</small><small data-dose-unit></small></div>
                            <div class="form-group prescription-field-duration" data-prescription-only><label>Duración (días) *</label><input class="form-control" name="detalles[{{ $index }}][duracion_dias]" type="number" min="1" max="365" step="1" value="{{ $detail['duracion_dias'] ?? '' }}" required data-treatment-duration></div>
                            <div class="form-group prescription-field-frequency" data-prescription-only><label>Frecuencia *</label><input class="form-control" name="detalles[{{ $index }}][frecuencia]" maxlength="100" placeholder="Cada 8 horas" value="{{ $detail['frecuencia'] ?? '' }}" required data-prescription-frequency></div>
                            <div class="form-group prescription-field-quantity" data-prescription-only><label>Cantidad prescrita *</label><input class="form-control" name="detalles[{{ $index }}][cantidad_indicada]" type="number" min="0.01" step="0.01" value="{{ $detail['cantidad_indicada'] ?? '' }}" required data-prescribed-quantity></div>
                            <div class="form-group dispensation-field-delivery dispensation-transcription-status"><label for="dispensation-quantity-{{ $index }}">Cantidad a entregar *</label><input class="form-control" id="dispensation-quantity-{{ $index }}" name="detalles[{{ $index }}][cantidad]" type="number" min="0.01" step="0.01" value="{{ $detail['cantidad'] ?? '' }}" data-prescription-product="{{ $detail['producto_id'] ?? '' }}" required data-delivery-quantity><small data-delivery-quantity-help>Puede ser parcial; no supera el saldo pendiente.</small></div>
                            <div class="form-group detail-instructions prescription-field-instructions" data-prescription-only><label>Indicaciones</label><input class="form-control" name="detalles[{{ $index }}][indicaciones]" maxlength="1000" value="{{ $detail['indicaciones'] ?? '' }}"></div>
                            <button class="icon-btn detail-remove prescription-field-remove" type="button" aria-label="Eliminar medicamento" @disabled(count($detailRows) === 1)><i class="fa-solid fa-trash"></i></button>
                        </div>
                    @endforeach
                </div>
                <p class="muted" data-dispensation-empty>Selecciona un paciente para cargar sus recetas vigentes.</p>
                <div class="form-group">
                    <label for="dispensation-observation">Observación de dispensación</label>
                    <textarea class="form-control" id="dispensation-observation" name="observacion" rows="2" maxlength="1000">{{ old('observacion') }}</textarea>
                    @error('observacion')<small class="field-error">{{ $message }}</small>@enderror
                </div>
                <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-circle-check"></i> Confirmar dispensación</button>
            </form>
            <template id="dispensation-detail-template">
                <div class="detail-row prescription-detail-row dispensation-prescription-row" data-dispensation-row data-prescription-row>
                    <header class="dispensation-prescription-heading" data-dispensation-prescription-heading hidden>
                        <strong data-dispensation-product-name></strong>
                        <div class="dispensation-fefo-summary" data-prescription-fefo role="status" aria-live="polite" hidden></div>
                    </header>
                    <x-product-search id="dispensation-product-template" field-key="producto_id" barcode-url="{{ route('products.barcode.lookup') }}" fefo-url="{{ route('products.api.fefo', 0) }}" field-class="prescription-field-product" search-label="Medicamento *" search-help="Busca el medicamento indicado en la receta y selecciona un resultado." :prescription="true" :show-availability="false" />
                    <div class="form-group prescription-field-dose" data-prescription-only><label>Dosis por toma *</label><input class="form-control" data-name="unidades_por_toma" type="number" min="0.01" step="0.01" required data-dose-amount><small>Cantidad administrada en cada toma; la frecuencia indica el intervalo entre dosis.</small><small data-dose-unit></small></div>
                    <div class="form-group prescription-field-duration" data-prescription-only><label>Duración (días) *</label><input class="form-control" data-name="duracion_dias" type="number" min="1" max="365" step="1" required data-treatment-duration></div>
                    <div class="form-group prescription-field-frequency" data-prescription-only><label>Frecuencia *</label><input class="form-control" data-name="frecuencia" maxlength="100" placeholder="Cada 8 horas" required data-prescription-frequency></div>
                    <div class="form-group prescription-field-quantity" data-prescription-only><label>Cantidad prescrita *</label><input class="form-control" data-name="cantidad_indicada" type="number" min="0.01" step="0.01" required data-prescribed-quantity></div>
                    <div class="form-group dispensation-field-delivery dispensation-transcription-status"><label>Cantidad a entregar *</label><input class="form-control" data-name="cantidad" type="number" min="0.01" step="0.01" required data-delivery-quantity><small data-delivery-quantity-help>Puede ser parcial; no supera el saldo pendiente.</small></div>
                    <div class="form-group detail-instructions prescription-field-instructions" data-prescription-only><label>Indicaciones</label><input class="form-control" data-name="indicaciones" maxlength="1000"></div>
                    <button class="icon-btn detail-remove prescription-field-remove" type="button" aria-label="Eliminar medicamento"><i class="fa-solid fa-trash"></i></button>
                </div>
            </template>
            <dialog class="controlled-auth-dialog" data-controlled-auth data-open-on-load="{{ $errors->has('autorizacion_qf') ? 'true' : 'false' }}" aria-labelledby="controlled-auth-title">
                <div class="controlled-auth-heading">
                    <span class="controlled-auth-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
                    <div>
                        <h2 id="controlled-auth-title">Validación de medicamento controlado</h2>
                        <p>Un Químico Farmacéutico debe autorizar esta dispensación.</p>
                    </div>
                </div>
                <div class="form-group">
                    <label for="dispensation-qf-rut">RUT del Químico Farmacéutico *</label>
                    <input class="form-control" id="dispensation-qf-rut" name="autorizador_rut" form="dispensation-form" type="text" maxlength="12" placeholder="12.345.678-9" autocomplete="username" data-rut-input aria-describedby="dispensation-qf-rut-error" data-controlled-authorizer-rut>
                    <small id="dispensation-qf-rut-error" class="field-error" data-rut-error aria-live="polite"></small>
                </div>
                <div class="form-group">
                    <label for="dispensation-qf-password">Contraseña del Químico Farmacéutico *</label>
                    <input class="form-control" id="dispensation-qf-password" name="autorizador_password" form="dispensation-form" type="password" maxlength="256" autocomplete="current-password" data-controlled-authorizer-password>
                </div>
                <p class="field-error" role="alert">@error('autorizacion_qf'){{ $message }}@enderror</p>
                <p class="muted">Se registrará el RUT validador junto al usuario que realiza la entrega. La contraseña no se almacena.</p>
                <div class="controlled-auth-actions">
                    <button class="btn btn-outline" type="button" data-controlled-auth-cancel>Cancelar</button>
                    <button class="btn btn-primary" type="button" data-controlled-auth-confirm>Validar y dispensar</button>
                </div>
            </dialog>
        </div>
    </section>
    @endif
    @if ($deliveryOrder)
        @php($order = $deliveryOrder['order'])
        <dialog class="delivery-order-dialog" data-dispensation-result-dialog data-open-on-load="true" aria-labelledby="dispensation-result-title">
            <header class="delivery-order-dialog-header">
                <span class="delivery-order-dialog-icon" aria-hidden="true"><img src="{{ asset('logo.jpg') }}" alt=""></span>
                <div>
                    <h2 id="dispensation-result-title">{{ $order->es_sos ? 'Tratamiento SOS' : 'Receta Médica' }}</h2>
                    <p>CESFAM Central · Atención Primaria de Salud · Operación auditada</p>
                </div>
                <form method="dialog">
                    <button class="delivery-order-dialog-close" type="submit" aria-label="Cerrar comprobante"><i class="fa-solid fa-xmark"></i></button>
                </form>
            </header>
            <div class="delivery-order-dialog-body">
                <div class="delivery-order-summary">
                    <div><span>N° ORDEN ENTREGA</span><strong>{{ $order->numero_orden }}</strong></div>
                    <div><span>{{ $order->es_sos ? 'TIPO DE DISPENSACIÓN' : 'FOLIO RECETA COTEJADA' }}</span><strong>{{ $order->es_sos ? 'TRATAMIENTO SOS' : ($order->numero_receta ?: 'Sin folio') }}</strong></div>
                    <div><span>FECHA Y HORA DESPACHO</span><strong>{{ \Illuminate\Support\Carbon::parse($order->fecha_hora)->format('d/m/Y H:i') }}</strong></div>
                </div>
                <div class="delivery-order-parties">
                    <article>
                        <span>PACIENTE TITULAR</span>
                        <strong>{{ $order->paciente }} ({{ $order->rut }})</strong>
                    </article>
                    <article>
                        <span>PERSONA QUE RETIRA</span>
                        <strong>{{ $order->es_sos ? 'SOS' : ($order->persona_autorizada ? $order->persona_autorizada.($order->persona_autorizada_rut ? ' ('.$order->persona_autorizada_rut.')' : '') : 'Paciente') }}</strong>
                        <small>{{ $order->es_sos ? 'Dispensación de emergencia SOS' : ($order->persona_autorizada ? 'Retiro por persona autorizada' : 'Retiro por titular') }}</small>
                    </article>
                </div>
                <div class="delivery-order-items-wrap">
                    <table class="delivery-order-items">
                        <thead>
                            <tr><th>Medicamento / Insumo</th><th>Lote FEFO</th><th>Vencimiento</th><th>Entregado</th><th>Etiqueta</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveryOrder['details'] as $detail)
                                <tr>
                                    <td>
                                        <strong>{{ $detail->producto }}</strong>
                                        <small>{{ $detail->dosis ?: 'Según indicación profesional' }} · {{ \App\Support\Prescriptions\MedicationFrequency::label($detail->horario) }}</small>
                                    </td>
                                    <td>{{ $detail->numero_lote }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($detail->fecha_vencimiento)->format('d/m/Y') }}</td>
                                    <td><strong>{{ \App\Support\Formatting\NumberFormat::format($detail->cantidad) }} {{ $detail->unidad_base_codigo }}</strong></td>
                                    <td>
                                        <button class="btn btn-outline btn-small" type="button" aria-label="Imprimir etiqueta de {{ $detail->producto }}" data-print-label-url="{{ route('delivery-orders.label', [$order->orden_entrega_id, $detail->entrega_detalle_id]) }}">
                                            <i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir etiqueta
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="delivery-order-print-status" data-print-status aria-live="polite">Se genera una etiqueta por medicamento y lote; cada solicitud de impresión queda auditada.</p>
                <footer class="delivery-order-dialog-footer">
                    <span>Dispensación confirmada por <strong>{{ $order->responsable }}</strong></span>
                    <div class="delivery-order-dialog-actions">
                        <button class="btn btn-outline" type="button" data-print-label-url="{{ route('delivery-orders.labels', $order->orden_entrega_id) }}"><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir etiquetas</button>
                        <button class="btn btn-outline" type="button" data-print-order-url="{{ route('delivery-orders.receipt', $order->orden_entrega_id) }}"><i class="fa-solid fa-print" aria-hidden="true"></i> Imprimir Orden</button>
                        <form method="dialog"><button class="btn btn-primary" type="submit">Finalizar y Nueva Atención</button></form>
                    </div>
                </footer>
            </div>
        </dialog>
    @endif
