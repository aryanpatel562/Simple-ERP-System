function validate() {
    if (document.myForm.username.value == "") {
        alert("Please provide your username!");
        document.myForm.username.focus();
        return false;
    }  
    if (document.myForm.password.value == "") {
        alert("Please provide your password!");
        document.myForm.password.focus();
        return false;
    }
return true;
}