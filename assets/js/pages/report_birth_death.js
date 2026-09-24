(function () {
    const eventType = document.getElementById('eventType');
    const causeField = document.getElementById('causeField');
    const causeInput = document.getElementById('causeInput');
    if (!eventType) return;

    function toggleCause() {
        const isDeath = eventType.value === 'death';
        causeField.style.display = isDeath ? '' : 'none';
        causeInput.required = isDeath;
        if (!isDeath) causeInput.value = '';
    }
    eventType.addEventListener('change', toggleCause);
    toggleCause();
})();
