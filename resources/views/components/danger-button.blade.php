<button {{ $attributes->merge(['type' => 'submit', 'class' => 'tn-btn tn-btn--danger']) }}>
    {{ $slot }}
</button>
