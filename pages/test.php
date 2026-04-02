<?php
// This is some basic PHP code to demonstrate something

echo "Hello, this is a test page!<br>";

// A simple loop
for ($i = 1; $i <= 5; $i++) {
    echo "Number: " . $i . "<br>";
}

// A functioN
function greet($name) {
    return "Hello, " . $name . "!";
}

echo greet("World");

// Adding a class to show more Bs code
class TestClass {
    public function saySomething() {
        return "This is a test class method!";
    }
}

$test = new TestClass();
echo "<br>" . $test->saySomething();
?>
