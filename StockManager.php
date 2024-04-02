class StockManager {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function updateStock(string $jsonProducts) {
        $products = json_decode($jsonProducts, true);

        //Inicia a transação com o banco
        $this->pdo->beginTransaction();

        try {
            foreach ($products as $product) {
                // Checar se existe o produto
                $query = $this->pdo->prepare("
                    SELECT id, quantity
                    FROM stock
                    WHERE product = :product
                    AND color = :color
                    AND size = :size
                    AND warehouse = :warehouse
                    AND availability_date = :availability_date
                ");
                // Inserir os valores na query
                $query->execute([
                    ':product' => $product['product'],
                    ':color' => $product['color'],
                    ':size' => $product['size'],
                    ':warehouse' => $product['warehouse'],
                    ':availability_date' => $product['availability_date'],
                ]);
                $stock = $query->fetch(PDO::FETCH_ASSOC);

                if ($stock) {
                    // Se já existe o produto na base de dados, atualiza a quantidade
                    $totalQuantity = $stock['quantity'] + $product['quantity'];
                    $query = $this->pdo->prepare("
                        UPDATE stock
                        SET quantity = :quantity
                        WHERE id = :id
                    ");
                    $query->execute([
                        ':quantity' => $totalQuantity,
                        ':id' => $stock['id'],
                    ]);
                } else {
                    // Se NÃO existe o produto na base de dados, insere o novo
                    $query = $this->pdo->prepare("
                        INSERT INTO stock (product, color, size, warehouse, availability_date, quantity)
                        VALUES (:product, :color, :size, :warehouse, :availability_date, :quantity)
                    ");
                    $query->execute([
                        ':product' => $product['product'],
                        ':color' => $product['color'],
                        ':size' => $product['size'],
                        ':warehouse' => $product['warehouse'],
                        ':availability_date' => $product['availability_date'],
                        ':quantity' => $product['quantity'],
                    ]);
                }
            }

            // Commita as alterações no banco de dados
            $this->pdo->commit();
            echo "Estoque atualizado com sucesso!";
        } catch (PDOException $e) {
            // Reverte as alterações caso ocorra algum erro
            $this->pdo->rollBack();
            echo "Erro ao atualizar o estoque: " . $e->getMessage();
        }
    }
}

class StockController {
    public function updateStock() {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); // Método não permitido
            echo "Método não permitido. Apenas POST é permitido.";
            return;
        }

        $jsonProducts = file_get_contents('php://input');

        if (empty($jsonProducts)) {
            http_response_code(400); // Requisição inválida
            echo "JSON vazio. Por favor, envie os dados JSON no corpo da requisição.";
            return;
        }

        $pdo = new PDO('mysql:host=localhost;dbname=database_name', 'username', 'password');
        
        $stockManager = new StockManager($pdo);

        $stockManager->updateStock($jsonProducts);

        http_response_code(200);
        echo "Operação de atualização de estoque concluída com sucesso!";
    }
}
?>