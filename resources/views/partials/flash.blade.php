@php
    $notification = null;
    $messages = $errors->all();
    $stockShortageMessage = 'Stock insuficiente para la dispensación.';

    if ($errors->any()) {
        $notification = in_array($stockShortageMessage, $messages, true)
            ? [
                'type' => 'error',
                'title' => 'Dispensación no realizada.',
                'message' => $stockShortageMessage,
            ]
            : [
                'type' => 'error',
                'title' => 'Revisa la información ingresada:',
                'messages' => $messages,
            ];
    } elseif (session()->has('error')) {
        $notification = ['type' => 'error', 'title' => 'Ocurrió un problema.', 'message' => session('error')];
    } elseif (session()->has('warning')) {
        $notification = ['type' => 'warning', 'title' => 'Atención.', 'message' => session('warning')];
    } elseif (session()->has('info')) {
        $notification = ['type' => 'info', 'title' => 'Información.', 'message' => session('info')];
    } elseif (session()->has('success') || session()->has('status')) {
        $notification = [
            'type' => 'success',
            'title' => 'Operación realizada con éxito.',
            'message' => session('success', session('status')),
        ];
    }

    $notificationIcons = [
        'success' => 'fa-check',
        'error' => 'fa-triangle-exclamation',
        'warning' => 'fa-triangle-exclamation',
        'info' => 'fa-circle-info',
    ];
@endphp

@if ($notification)
    <div class="notification-toast notification-toast-{{ $notification['type'] }}" role="{{ $notification['type'] === 'error' ? 'alert' : 'status' }}" aria-live="{{ $notification['type'] === 'error' ? 'assertive' : 'polite' }}" aria-atomic="true" data-notification-toast data-notification-persistent="{{ $notification['type'] === 'error' ? 'true' : 'false' }}">
        <span class="notification-toast-icon" aria-hidden="true"><i class="fa-solid {{ $notificationIcons[$notification['type']] }}"></i></span>
        <div class="notification-toast-copy">
            <strong>{{ $notification['title'] }}</strong>
            @if (isset($notification['messages']))
                <ul>
                    @foreach ($notification['messages'] as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @else
                <p>{{ $notification['message'] }}</p>
            @endif
        </div>
        <button class="notification-toast-dismiss" type="button" aria-label="Cerrar notificación" data-notification-dismiss>
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
@endif
