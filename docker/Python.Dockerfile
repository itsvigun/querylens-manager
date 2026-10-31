FROM ghcr.io/astral-sh/uv:0.8.22 AS uv
FROM python:3.14.8-slim-bookworm
COPY --from=uv /uv /usr/local/bin/uv
WORKDIR /upstream
COPY --from=upstream . /upstream
RUN uv sync --frozen --no-dev
ENV PATH="/upstream/.venv/bin:$PATH" PYTHONPATH=/upstream
CMD ["python", "/checks/verify.py"]
