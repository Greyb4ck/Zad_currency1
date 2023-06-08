<?php
class CurrencyConverter {
    private $exchangeRates;

    public function __construct() {
        $exchangeRates = $this->fetchExchangeRates();
        $this->exchangeRates = $exchangeRates;
    }

    // Fetch exchange rates from the database
    private function fetchExchangeRates() {
        $currencyData = $this->fetchCurrencyData();
        $exchangeRates = [];
    
        $exchangeRates['PLN'] = 1; // Set initial value for PLN
    
        foreach ($currencyData as $currency) {
            $code = $currency['code_currency'];
            $mid = $currency['mid_currency'];
            
            $exchangeRates[$code] = $mid; 
        }
    
        return $exchangeRates;
    }
    // Fetch currency data from the database
    public function fetchCurrencyData() {
        global $conn; 
    
        $sql = "SELECT * FROM Currency";
        $result = $conn->query($sql);
    
        $currencyData = array();
    
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $currencyData[] = $row;
            }
        }
    
        return $currencyData;
    }

    // Generate and execute the currency conversion form
    public function generateCurrencyForm() {
        if (isset($_POST['exchange'])) {
            $this->processCurrencyForm();
            header("Location: currency/index.php");
            exit();
        } else {
            $this->displayCurrencyForm();
        }
        
    }
    // Display conversion form
    public function displayCurrencyForm() {
        $currencyData = $this->fetchCurrencyData();
    
        // Generate a token
        $token = md5(uniqid(rand(), true));
    
        // Save token in session
        $_SESSION['form_token'] = $token;
    
        $html = '<form action="#" method="POST">
                    <label>Select your currency</label>
                    <input type="hidden" name="form_token" value="' . $token . '">
                    <input type="number" name="amount" step="0.01" min="0" max="999999999999999999999999999999">
                    <select name="currency"><option value=PLN>PLN</option>';
    
        foreach ($currencyData as $currency) {
            $code = $currency['code_currency'];
            $html .= "<option value='$code'>$code</option>";
        }
    
        $html .= '</select><label>Select the currency you want</label><select name="ex_currency"><option value=PLN>PLN</option>';
    
        foreach ($currencyData as $currency) {
            $code = $currency['code_currency'];
            $html .= "<option value='$code'>$code</option>";
        }
    
        $html .= '</select><input type="submit" name="exchange" value="Exchange"></form>';
        echo $html;
    }

    public function processCurrencyForm() {

        if (!empty($_POST['form_token']) && $_POST['form_token'] === $_SESSION['form_token']) {
            // Token is valid, process the form
        
            $amount = $_POST['amount'];
            $currency = $_POST['currency'];
            $exCurrency = $_POST['ex_currency'];
        
            $this->convertCurrency($amount, $currency, $exCurrency);
        
            // Remove the token after processing the form
            unset($_SESSION['form_token']);
        } else {
            //echo "Token is invalid";
        }
    }
    // Print the exchange rates
    public function printExchangeRates() {
        print_r($this->exchangeRates);
    }
    // Convert the currency based on the provided values
    public function convertCurrency($amount, $fromCurrency, $toCurrency) {

        // Check if $amount is not a number
        if (!is_numeric($amount)) {
            $amount = 1; // Set $amount to 1
        }

        $val = $this->checkIfExchangeHistoryTableExists();

        if ($val == FALSE) {
            $this->createExchangeHistoryTable();
        }
        
        $exchangeRateFrom = $this->exchangeRates[$fromCurrency];
        $exchangeRateTo = $this->exchangeRates[$toCurrency];
            
        if (array_key_exists($fromCurrency, $this->exchangeRates) && array_key_exists($toCurrency, $this->exchangeRates)) {
            // Convert the amount from the source currency to the target currency
            $convertedAmount = $amount * ($exchangeRateFrom / $exchangeRateTo);
        } else {
            // Handle invalid currencies here
            return;
        }
        
        // Insert the converted amount to database
        $this->insertExchangeHistoryData($fromCurrency, $toCurrency, $amount, $convertedAmount);
        //echo "Converted amount: $convertedAmount";
        
    }
    // Create the exchange history table in the database
    public function createExchangeHistoryTable() {
        global $conn;

        if ($this->checkIfExchangeHistoryTableExists()) {
            return;
        }
    
        $sql = "CREATE TABLE ExchangeHistory (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            code_fromCurrency VARCHAR(255) CHARACTER SET utf16 COLLATE utf16_polish_ci,
            code_toCurrency VARCHAR(255) CHARACTER SET utf16 COLLATE utf16_polish_ci,
            amount DECIMAL(65,4)UNSIGNED,
            convertedAmount DECIMAL(65,4) UNSIGNED
        )";
    
        if (!mysqli_query($conn, $sql)) {
            die('Error creating table: ' . mysqli_error($conn));
        }
    }
    // Insert exchange history data into the database
    public function insertExchangeHistoryData($fromCurrency, $toCurrency, $amount, $convertedAmount) {
        global $conn;
    
        $insertSql = "INSERT INTO ExchangeHistory (code_fromCurrency, code_toCurrency, amount, convertedAmount) 
                      VALUES (?, ?, ?, ?)";
    
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param("ssdd", $fromCurrency, $toCurrency, $amount, $convertedAmount);
    
        if ($stmt->execute()) {
            //echo "Inserted ExchangeHistoryData";
        } else {
            echo "Error inserting ExchangeHistory: " . $stmt->error . "\n";
        }
    
        $stmt->close();
    }
    // Check if the exchange history table exists in the database
    public function checkIfExchangeHistoryTableExists() {
        global $conn;
    
        $result = mysqli_query($conn, "SHOW TABLES LIKE 'ExchangeHistory'");
        return mysqli_num_rows($result) > 0;
    }
    // Drop the exchange history table from the database
    public function dropExchangeHistoryTable() {
        global $conn;
    
        $sql = "DROP TABLE ExchangeHistory";
    
        if (!mysqli_query($conn, $sql)) {
            die('Error deleting ExchangeHistory table: ' . mysqli_error($conn));
        }
    }
    // Fetch exchange history data from the database
    public function fetchExchangeHistoryData() {
        global $conn; 
    
        $sql = "SELECT * FROM ExchangeHistory";
        $result = $conn->query($sql);
    
        $currencyData = array();
    
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $currencyData[] = $row;
            }
        }
    
        return $currencyData;
    }
    // Generate and display the exchange history list
    function generateExchangeHistoryList() {
        
        $val = $this->checkIfExchangeHistoryTableExists();

        if ($val == FALSE) {
            $this->createExchangeHistoryTable();
        }

        $currencyData = $this->fetchExchangeHistoryData();
    
        $html = '<table>';
        $html .= '<tr><th>ID</th><th>Original Currency</th><th>Desired Currency</th><th>Amount</th><th>Converted Amount</th></tr>';
        
        foreach ($currencyData as $currency) {
            $id = $currency['ID'];
            $code_fromCurrency = $currency['code_fromCurrency'];
            $code_toCurrency = $currency['code_toCurrency'];
            $amount = $currency['amount'];
            $convertedAmount = $currency['convertedAmount'];
    
            $html .= "<tr><td>$id</td><td>$code_fromCurrency</td><td>$code_toCurrency</td><td>$amount</td><td>$convertedAmount</td></tr>";
        }
    
        $html .= '</table>';
    
        echo $html;
    }

}
?>