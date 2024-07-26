const images = [
    'galaxia1.webp',
    'planeta1.webp',
    'cometa1.webp',
    'saturno1.webp',
    'galaxia2.webp',
    'luna1.webp',
    'planeta2.webp',
    'saturno2.webp',
    'pluton1.webp',
];

let currentIndex = 0;
const message = document.getElementById('message');

// Función para barajar las imágenes
function shuffle(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

// Barajar las imágenes al iniciar
let shuffledImages = shuffle([...images]);

// Función para generar una cadena única
function uniqueString() {
    return new Date().getTime();
}

function changeObject() {
    const image = document.getElementById('celestial-object');
    currentIndex = (currentIndex + 1) % shuffledImages.length;
    image.src = `${shuffledImages[currentIndex]}?v=${uniqueString()}`;
    image.alt = shuffledImages[currentIndex].split('.')[0];  // Usa el nombre del archivo como alt
}

function toggleMessage() {
    message.style.display = message.style.display === 'none' ? 'block' : 'none';
}

setInterval(changeObject, 5970);  // Cambia la imagen cada 5.97 segundos
setInterval(toggleMessage, 4150);  // Alterna el mensaje cada 4.15 segundos

// Forzar recarga completa de las imágenes al cargar la página
window.addEventListener('load', function() {
    const image = document.getElementById('celestial-object');
    image.src = `${shuffledImages[currentIndex]}?v=${uniqueString()}`;
});
