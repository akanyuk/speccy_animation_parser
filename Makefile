BINARY_NAME := $(shell git config --get remote.origin.url | awk -F/ '{print $$5}' | awk -F. '{print $$1}')
BINARY_VERSION := $(shell git describe --tags)

ABS_PATH := $(dir $(realpath $(lastword $(MAKEFILE_LIST))))
ifeq ($(OS), Windows_NT)
	ABS_PATH = $(PWD)
	ifneq ($(shell whereis cygpath), "cygpath:")
		ABS_PATH = $(shell cygpath -w $(CURDIR))
	endif
endif

ifndef DOCKER_PORT
	DOCKER_PORT := 8080
endif

PRINTF_FORMAT := "\033[35m%-18s\033[33m %s\033[0m\n"

.PHONY: all vendor docker-build docker-run docker-down help

all: docker-build

vendor: ## Get dependencies according to composer.json
	composer update

docker-build: vendor ## Docker image generation
	@printf $(PRINTF_FORMAT) BINARY_NAME: $(BINARY_NAME)
	@printf $(PRINTF_FORMAT) BINARY_VERSION: $(BINARY_VERSION)

	docker build --tag $(BINARY_NAME):$(BINARY_VERSION) .

docker-run: docker-build docker-down ## Docker image run
	@printf $(PRINTF_FORMAT) DOCKER_PORT: $(DOCKER_PORT)
	docker run --detach --name $(BINARY_NAME) -p $(DOCKER_PORT):80 $(BINARY_NAME):$(BINARY_VERSION)

docker-run-dev: docker-build docker-down ## Docker run in dev mode
	@printf $(PRINTF_FORMAT) DOCKER_PORT: $(DOCKER_PORT)
	@printf $(PRINTF_FORMAT) ABS_PATH: "$(ABS_PATH)"
	docker run --detach --name $(BINARY_NAME) -p $(DOCKER_PORT):80 -v "$(ABS_PATH)":/www $(BINARY_NAME):$(BINARY_VERSION)

docker-down: ## Docker stop
	docker container rm --force $(BINARY_NAME) || true

help: ## Display available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'
