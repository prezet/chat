# Laravel AI Chat Starter Kit
<div align="center">
    <img src="https://github.com/prezet/chat/blob/main/art/chat_dashboard2?raw=true" alt="Chat Screenshot">
    <img src="https://github.com/prezet/chat/blob/main/art/chat_dashboard1?raw=true" alt="Chat Screenshot">
</div>

This starter kit integrates the [Vercel AI SDK UI](https://sdk.vercel.ai/docs/ai-sdk-ui/overview) with a Laravel backend powered by the [Prism](https://prism.echolabs.dev/) package, providing a robust foundation for building AI-driven chat experiences.

Built on the [Laravel React Starter Kit](https://github.com/laravel/react-starter-kit) with UI elements from Vercel's [AI Templates](https://vercel.com/templates/ai), this kit combines a polished React-based front end with the familiar Laravel ecosystem, enabling features like persistent conversations and tool calling.

## Key Features

- **Persistent Conversations**: Store chat history in the database for seamless continuity across sessions.
- **Tool Calling**: Allow the AI to interact with external tools or APIs during conversations.
- **Real-Time Streaming**: Deliver streamed chat responses using Server-Sent Events (SSE).
- **Prism Integration**: Utilize Prism for a clean, high-level interface to manage LLM interactions.

## Installation

### Steps

Follow these steps to set up the starter kit locally:

1. **Clone the repository and navigate to the project directory**:

   ```bash
   git clone https://github.com/prezet/chat.git && cd chat
   ```

2. **Install Composer and NPM dependencies**:

   ```bash
   composer install && npm install
   ```

3. **Copy the .env.example file and configure environment variables**:

   ```bash
   cp .env.example .env && php artisan key:generate
   ```

    Edit the `.env` file to include your database credentials, LLM API keys, and desired LLM settings. For example, to use OpenAI:

    ```bash
    PRISM_PROVIDER=openai
    PRISM_MODEL=gpt-4o
    OPENAI_API_KEY=YOUR_OPENAI_API_KEY
    ```

    or to use Gemini:

    ```bash
    PRISM_PROVIDER=gemini
    PRISM_MODEL=gemini-2.0-flash
    GEMINI_API_KEY=YOUR_GEMINI_API_KEY
   ```

   or to use Ollama:

    ```bash
    PRISM_PROVIDER=ollama
    PRISM_MODEL=llama3.1
   ```

4. **Run database migrations**:

   ```bash
   php artisan migrate --seed
   ```

5. **Start the development server**:

   ```bash
   composer run dev
   ```

    - This command should launch both the Laravel server and the Vite development server.

## Usage

After starting the servers, visit `http://localhost:8000` and login using the default credentials to access the chat application.

- **Email**: `test@example.com`
- **Password**: `password`

## Credits

- [All Contributors](https://github.com/prezet/chat/contributors)

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more information.
