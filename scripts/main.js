// scripts/main.js

// Select all navigation list items
const navItems = document.querySelectorAll(".navigation li");

// Function to handle active link state
function activateLink() {
    navItems.forEach((item) => item.classList.remove("hovered"));
    this.classList.add("hovered");
}

// Add event listeners to navigation items
navItems.forEach((item) => item.addEventListener("mouseenter", activateLink));

// Toggle navigation and main content
const toggleButton = document.querySelector(".toggle-button");
const navigation = document.querySelector(".navigation");
const mainContent = document.querySelector(".main");

toggleButton.onclick = function () {
    navigation.classList.toggle("active");
    mainContent.classList.toggle("active");
};