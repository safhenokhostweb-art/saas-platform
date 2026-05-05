from openai import OpenAI

client = OpenAI(
    api_key="sk-dOXXUACTSZicnMWaVexYvHiWCHTyUlzSkZlPgbAkk4pMDb6Z",
    base_url="https://api.gapgpt.app/v1"
)

resp = client.chat.completions.create(
    model="gpt-4o",
    messages=[{"role": "user", "content": "سلام!"}]
)
print(resp.choices[0].message.content)
