<?php

class SectionManager
{
    private $pdo;

    // Constructor with Dependency Injection
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Add a new section
    public function addSection(string $title, string $content): bool
    {
        $sql = "INSERT INTO sections (title, content) VALUES (:title, :content)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['title' => $title, 'content' => $content]);
    }

    // Edit a section by ID
    public function editSection(int $id, string $title, string $content): bool
    {
        $sql = "UPDATE sections SET title = :title, content = :content WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id, 'title' => $title, 'content' => $content]);
    }

    // Delete a section by ID
    public function deleteSection(int $id): bool
    {
        $sql = "DELETE FROM sections WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    // List all sections
    public function listSections(): array
    {
        $sql = "SELECT id, title, content FROM sections";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Show a specific section by ID
    public function showSection(int $id): array
    {
        $sql = "SELECT id, title, content FROM sections WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
