public class Product {
        private int id; // Унікальний ідентифікатор товару
        private String name; // Назва товару
        private double price; // Ціна товару
        private String description; // Опис товару

        // Конструктор класу
        public Product(int id, String name, double price, String description) {
            this.id = id;
            this.name = name;
            this.price = price;
            this.description = description;
        }

    public int getId() {
        return id;
    }
    public void setId(int id) {
        this.id = id;
    }
    public String getName() {
        return name;
    }
    public void setName(String name) {
        this.name = name;
    }
    public double getPrice() {
        return price;
    }
    public void setPrice(double price) {
        this.price = price;
    }
    public String getDescription() {
        return description;
    }
    public void setDescription(String description) {
        this.description = description;
    }
    @Override
    public String toString() {
        return "Товар{" +
                "id=" + id +
                ", назва='" + name + '\'' +
                ", ціна=" + price +
                ", опис='" + description + '\'' +
                '}';
    }
}

