// Function to copy text to clipboard
function copyToClipboard(text) {
  navigator.clipboard
    .writeText(text)
    .then(() => {
      alert("تم النسخ: " + text);
    })
    .catch(() => {
      alert("فشل النسخ");
    });
}

// Function to toggle password visibility
function togglePasswordVisibility(button) {
  const passwordInput = button.previousElementSibling;
  const eyeIcon = button.querySelector("img");

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    eyeIcon.src = "./assets/icons/eye.svg";
  } else {
    passwordInput.type = "password";
    eyeIcon.src = "./assets/icons/eye-closed.svg";
  }
}

// Function to update permissions
function updatePermission(adminId, permission, isChecked) {
  fetch(
    `./members/update/update_permission.php?id=${adminId}&permission=${permission}&value=${
      isChecked ? 1 : 0
    }`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        alert("تم تحديث الصلاحية بنجاح");
      } else {
        alert("فشل تحديث الصلاحية");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
    });
}

// Function to delete an admin
function deleteAdmin(adminId) {
  if (confirm("هل أنت متأكد من حذف هذا المشرف؟")) {
    fetch(`./members/delete/delete_admin.php?id=${adminId}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          alert("تم حذف المشرف بنجاح");
          window.location.reload(); // Reload the page to reflect the changes
        } else {
          alert("فشل حذف المشرف: " + (data.error || ""));
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("حدث خطأ أثناء محاولة حذف المشرف");
      });
  }
}

// Function to generate a random password
function generatePassword() {
  const chars =
    "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
  let newPassword = "";
  for (let i = 0; i < 6; i++) {
    newPassword += chars[Math.floor(Math.random() * chars.length)];
  }
  return newPassword;
}

// Function to populate the password field on page load
function populatePassword() {
  const passwordField = document.getElementById("password");
  if (passwordField) {
    passwordField.value = generatePassword();
  }
}

// Call the populatePassword function when the page loads
window.onload = populatePassword;

// Existing functions for copying and regenerating password
function copyCredentials(username, password) {
  const text = `username: ${username}\npassword: ${password}`;
  navigator.clipboard
    .writeText(text)
    .then(() => {
      alert("تم نسخ بيانات الاعتماد بنجاح!");
      window.location.href = "../members.php";
    })
    .catch(() => {
      alert("فشل نسخ بيانات الاعتماد.");
    });
}

// Existing functions for copying username and password and url for system quality
function copyUsernameAndPassword(name,username,password) {
  const text = `Link to the evaluation system : https://erp.cit.edu.ly/quality-system/login.php \n\nname: ${name}\nusername: ${username}\npassword: ${password}`;
  navigator.clipboard
    .writeText(text)
    .then(() => {
      alert("تم نسخ بيانات الاعتماد بنجاح!");
    })
    .catch(() => {
      alert("فشل نسخ بيانات الاعتماد.");
    });
}

function copyUsername() {
  const username = document.getElementById("username").value;
  navigator.clipboard
    .writeText(username)
    .then(() => {
      alert("تم نسخ اسم المستخدم بنجاح!");
    })
    .catch(() => {
      alert("فشل نسخ اسم المستخدم.");
    });
}

function copyPassword() {
  const password = document.getElementById("password").value;
  navigator.clipboard
    .writeText(password)
    .then(() => {
      alert("تم نسخ كلمة المرور بنجاح!");
      window.location.href = "../members.php";
    })
    .catch(() => {
      alert("فشل نسخ كلمة المرور.");
    });
}

function regeneratePassword() {
  const passwordField = document.getElementById("password");
  if (passwordField) {
    passwordField.value = generatePassword();
  }
}
