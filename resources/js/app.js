import './bootstrap';
import '../css/app.css';

document.addEventListener("livewire:exception", function (event) {
    const exception = event.detail.exception;

    if (exception && exception.message && exception.message.includes("CSRF token mismatch")) {
        event.preventDefault(); // Stop Livewire's default 419 handling
        alert("Your session has expired. Please refresh the page.");
    }
});
