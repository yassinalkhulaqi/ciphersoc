FROM python:3.12-slim
WORKDIR /agent
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt
COPY src/ ./src/
ENV CIPHERSOC_URL=http://nginx/api/v1
CMD ["python", "-m", "src.main"]
